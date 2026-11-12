<?php

namespace App\Http\Controllers;

use App\Exceptions\EnrollmentRuleException;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\Term;
use App\Services\EnrollmentRules;
use App\Services\EnrollmentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Support\Like;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $termId = $request->input('term', Term::current()?->id);

        $base = Enrollment::query()
            ->when($termId, fn ($q) => $q->whereHas('classOffering', fn ($c) => $c->where('term_id', $termId)));

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        // The page opens on the work to do: Pending while anything waits, otherwise everything.
        $status = (string) $request->input('status', ($counts[Enrollment::STATUS_PENDING] ?? 0) > 0 ? Enrollment::STATUS_PENDING : 'all');
        if ($status !== 'all' && ! in_array($status, Enrollment::STATUSES, true)) {
            $status = 'all';
        }

        $requiredCount = \App\Models\Requirement::where('is_active', true)->where('is_required', true)->count();

        $filtered = (clone $base)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('program'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('program_id', (int) $request->input('program'))))
            // Quick filter: students whose required documents are not all verified yet.
            ->when($request->input('docs') === 'incomplete' && $requiredCount > 0, fn ($q) => $q->whereNotIn('student_id',
                StudentRequirement::query()->select('student_id')
                    ->where('status', StudentRequirement::STATUS_VERIFIED)
                    ->whereHas('requirement', fn ($r) => $r->where('is_active', true)->where('is_required', true))
                    ->groupBy('student_id')->havingRaw('count(*) >= ?', [$requiredCount])))
            ->when($request->filled('search'), function ($query) use ($request) {
                foreach (preg_split('/\s+/', trim((string) $request->input('search'))) as $word) {
                    $like = Like::contains($word);
                    $query->where(function ($w) use ($like) {
                        $w->whereHas('student', fn ($s) => $s->where('student_number', 'like', $like)
                            ->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
                            ->orWhereHas('classOffering', fn ($c) => $c->where('class_code', 'like', $like)
                                ->orWhereHas('subject', fn ($s) => $s->where('code', 'like', $like)->orWhere('title', 'like', $like)));
                    });
                }
            });

        // One block per student (a student's requests are never split across pages), newest activity first.
        $students = (clone $filtered)
            ->select('student_id')
            ->selectRaw('max(requested_at) as last_requested, max(id) as last_id')
            ->groupBy('student_id')
            ->orderByDesc('last_requested')->orderByDesc('last_id')
            ->paginate(10)
            ->withQueryString();
        $studentIds = $students->pluck('student_id')->all();

        $requests = (clone $filtered)
            ->whereIn('student_id', $studentIds)
            ->with([
                'student.program',
                'requester',
                'reviewer',
                'classOffering' => fn ($q) => $q->with(['subject', 'meetings', 'term'])
                    ->withCount(['enrollments as seats_taken' => fn ($e) => $e->whereIn('status', [Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])]),
            ])
            ->get()
            ->groupBy('student_id');

        // Units each student holds or asks for this term (pending, approved, enrolled), for the unit limit.
        $units = $termId
            ? Enrollment::query()
                ->join('class_offerings', 'class_offerings.id', '=', 'enrollments.class_offering_id')
                ->join('subjects', 'subjects.id', '=', 'class_offerings.subject_id')
                ->whereIn('enrollments.student_id', $studentIds)
                ->whereIn('enrollments.status', [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])
                ->where('class_offerings.term_id', $termId)
                ->selectRaw('enrollments.student_id, sum(subjects.units) as total')
                ->groupBy('enrollments.student_id')->pluck('total', 'enrollments.student_id')->all()
            : [];

        $groups = collect($studentIds)->map(fn ($id) => [
            'student' => $requests[$id]->first()->student,
            'requests' => $requests[$id]->sortBy(fn (Enrollment $e) => $e->classOffering->class_code)->values(),
            'units' => (float) ($units[$id] ?? 0),
        ]);

        // Verified required documents per student on this page, to help the approver.
        $verified = StudentRequirement::whereIn('student_id', $studentIds)
            ->where('status', StudentRequirement::STATUS_VERIFIED)
            ->whereHas('requirement', fn ($q) => $q->where('is_active', true)->where('is_required', true))
            ->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total', 'student_id')->all();

        return view('enrollments.index', [
            'groups' => $groups,
            'students' => $students,
            'status' => $status,
            'maxUnits' => (float) \App\Models\Setting::get('max_units_per_term', '24'),
            'counts' => $counts,
            'terms' => Term::with('academicYear')->orderByDesc('start_date')->get(),
            'termId' => $termId,
            'verified' => $verified,
            'requiredCount' => $requiredCount,
            'programs' => \App\Models\Program::orderBy('code')->pluck('code', 'id'),
        ]);
    }

    /** Step 1: find the student. Step 2: choose classes for that student. */
    public function create(Request $request)
    {
        $term = Term::with('academicYear')->where('is_current', true)->where('enrollment_open', true)->whereNull('closed_at')->first();

        $data = ['term' => $term, 'student' => null, 'results' => collect()];

        if ($request->filled('student')) {
            $student = Student::with('program')->find((int) $request->input('student'));

            if (! $student) {
                return redirect()->route('enrollments.create')->with('error', 'That student was not found or is archived.');
            }

            $data['student'] = $student;

            if ($term) {
                $context = EnrollmentRules::context($student, $term->id);

                $classes = ClassOffering::with(['subject', 'teacher', 'meetings', 'term'])
                    ->withCount(['enrollments as seats_taken' => fn ($e) => $e->whereIn('status', [Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])])
                    ->where('term_id', $term->id)
                    ->where('status', ClassOffering::STATUS_OPEN)
                    ->when($request->filled('q'), function ($query) use ($request) {
                        $like = Like::contains($request->input('q'));
                        $query->where(fn ($w) => $w->where('class_code', 'like', $like)
                            ->orWhereHas('subject', fn ($s) => $s->where('code', 'like', $like)->orWhere('title', 'like', $like)));
                    })
                    ->orderBy('class_code')
                    ->get();

                $existing = Enrollment::where('student_id', $student->id)
                    ->whereIn('class_offering_id', $classes->pluck('id'))->get()->keyBy('class_offering_id');

                $data['rows'] = $classes->map(fn ($class) => [
                    'class' => $class,
                    'existing' => $existing->get($class->id),
                    'problems' => $existing->has($class->id) ? [] : EnrollmentRules::problems($student, $class, $context),
                ]);
                $data['units'] = $context['units'];
                $data['maxUnits'] = $context['maxUnits'];
            }
        } elseif ($request->filled('q')) {
            $query = Student::query()->with('program')->where('status', Student::STATUS_ACTIVE);

            foreach (preg_split('/\s+/', trim((string) $request->input('q'))) as $word) {
                $like = Like::contains($word);
                $query->where(fn ($w) => $w->where('student_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like));
            }

            $data['results'] = $query->orderBy('last_name')->orderBy('first_name')->limit(10)->get();
        }

        return view('enrollments.create', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer'],
        ], ['class_ids.required' => 'Choose at least one class.', 'class_ids.min' => 'Choose at least one class.']);

        $student = Student::findOrFail($data['student_id']);

        try {
            $created = EnrollmentWorkflow::request($student, array_map('intval', $data['class_ids']), $request->user());
        } catch (EnrollmentRuleException $e) {
            return back()->withErrors(['enrollment' => $e->messages])->withInput();
        }

        return redirect()->route('enrollments.index')
            ->with('success', $created->count() . ' request' . ($created->count() === 1 ? '' : 's') . ' recorded as pending.');
    }

    public function approve(Request $request, Enrollment $enrollment)
    {
        return $this->run(fn () => EnrollmentWorkflow::approve($enrollment, $request->user()), 'Request approved. The seat is reserved.');
    }

    public function reject(Request $request, Enrollment $enrollment)
    {
        return $this->run(fn () => EnrollmentWorkflow::reject($enrollment, $request->user(), $this->reason($request)), 'Request rejected.');
    }

    public function enroll(Request $request, Enrollment $enrollment)
    {
        return $this->run(fn () => EnrollmentWorkflow::enroll($enrollment, $request->user()), 'Student enrolled. They now appear on the class roster.');
    }

    public function cancel(Request $request, Enrollment $enrollment)
    {
        return $this->run(fn () => EnrollmentWorkflow::cancel($enrollment, $request->user(), $this->reason($request)), 'Request cancelled.');
    }

    public function reopen(Request $request, Enrollment $enrollment)
    {
        return $this->run(fn () => EnrollmentWorkflow::reopen($enrollment, $request->user()), 'Request reopened as pending.');
    }

    /**
     * Approve or enroll several requests at once. Each one runs through the same checks as a single
     * action (seats, units, schedule conflicts); the ones that pass are saved, the others are listed.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,enroll'],
            // Either single requests, or whole students (all their pending / approved requests in the term).
            'ids' => ['required_without:students', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'students' => ['required_without:ids', 'array', 'max:100'],
            'students.*' => ['integer'],
            'term' => ['nullable', 'integer'],
        ], [
            'ids.required_without' => 'Tick at least one student first.',
            'students.required_without' => 'Tick at least one student first.',
        ]);

        $wanted = $data['action'] === 'approve' ? Enrollment::STATUS_PENDING : Enrollment::STATUS_APPROVED;
        $done = 0;
        $problems = [];
        $requests = Enrollment::with(['student', 'classOffering.subject'])
            ->when(! empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']))
            ->when(! empty($data['students']), fn ($q) => $q->whereIn('student_id', $data['students'])->where('status', $wanted)
                ->when(! empty($data['term']), fn ($e) => $e->whereHas('classOffering', fn ($c) => $c->where('term_id', (int) $data['term']))))
            ->get();

        if ($requests->isEmpty()) {
            return back()->withErrors(['enrollment' => $data['action'] === 'approve'
                ? 'The selected students have no pending requests to approve.'
                : 'The selected students have no approved requests to enroll.']);
        }

        foreach ($requests as $enrollment) {
            try {
                $data['action'] === 'approve'
                    ? EnrollmentWorkflow::approve($enrollment, $request->user())
                    : EnrollmentWorkflow::enroll($enrollment, $request->user());
                $done++;
            } catch (EnrollmentRuleException $e) {
                $who = ($enrollment->student?->full_name ?? 'Student') . ' · ' . $enrollment->classOffering->subject->code;
                $problems[] = $who . ': ' . implode(' ', $e->messages);
            }
        }

        $verb = $data['action'] === 'approve' ? 'approved' : 'enrolled';
        $redirect = back();
        if ($done > 0) {
            $redirect = $redirect->with('success', $done . ' ' . ($done === 1 ? 'request' : 'requests') . " {$verb}.");
        }

        return $problems === [] ? $redirect : $redirect->withErrors(['enrollment' => $problems]);
    }

    private function run(callable $action, string $success)
    {
        try {
            $action();
        } catch (EnrollmentRuleException $e) {
            return back()->withErrors(['enrollment' => $e->messages]);
        }

        return back()->with('success', $success);
    }

    private function reason(Request $request): string
    {
        $validator = Validator::make($request->all(), ['reason' => ['required', 'string', 'min:5', 'max:255']]);

        if ($validator->fails()) {
            throw new EnrollmentRuleException(['Please give a reason of at least 5 characters.']);
        }

        return trim($validator->validated()['reason']);
    }
}
