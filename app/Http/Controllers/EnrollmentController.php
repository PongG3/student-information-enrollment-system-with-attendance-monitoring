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

        $enrollments = (clone $base)
            ->with([
                'student.program',
                'requester',
                'reviewer',
                'classOffering' => fn ($q) => $q->with(['subject', 'meetings', 'term'])
                    ->withCount(['enrollments as seats_taken' => fn ($e) => $e->whereIn('status', [Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])]),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
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
            })
            ->latest('requested_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Verified required documents per student on this page, to help the approver.
        $studentIds = $enrollments->pluck('student_id')->unique()->all();
        $verified = StudentRequirement::whereIn('student_id', $studentIds)
            ->where('status', StudentRequirement::STATUS_VERIFIED)
            ->whereHas('requirement', fn ($q) => $q->where('is_active', true)->where('is_required', true))
            ->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total', 'student_id')->all();

        return view('enrollments.index', [
            'enrollments' => $enrollments,
            'counts' => $counts,
            'terms' => Term::with('academicYear')->orderByDesc('start_date')->get(),
            'termId' => $termId,
            'verified' => $verified,
            'requiredCount' => \App\Models\Requirement::where('is_active', true)->where('is_required', true)->count(),
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Tick at least one request first.']);

        $done = 0;
        $problems = [];
        $requests = Enrollment::with(['student', 'classOffering.subject'])->whereIn('id', $data['ids'])->get();

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
