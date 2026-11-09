<?php

namespace App\Http\Controllers;

use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\ScheduleChecker;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassOfferingController extends Controller
{
    public const MEETING_ROWS = 4;

    public function index(Request $request)
    {
        $currentTerm = Term::current();
        $termId = $request->input('term', $currentTerm?->id);

        $classes = ClassOffering::with(['subject', 'teacher', 'term', 'meetings'])
            ->withCount(['enrollments as seats_taken' => fn ($q) => $q->whereIn('status', [Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])])
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $like = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('class_code', 'like', $like)
                        ->orWhereHas('subject', fn ($s) => $s->where('code', 'like', $like)->orWhere('title', 'like', $like))
                        ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', $like));
                });
            })
            ->orderBy('class_code')
            ->paginate(15)
            ->withQueryString();

        return view('classes.index', [
            'classes' => $classes,
            'terms' => Term::with('academicYear')->orderByDesc('start_date')->get(),
            'termId' => $termId,
        ]);
    }

    public function create(Request $request)
    {
        $termId = $request->input('term', Term::current()?->id);
        $class = new ClassOffering([
            'term_id' => $termId,
            'section' => 'A',
            'capacity' => 40,
            'status' => ClassOffering::STATUS_OPEN,
            'class_code' => $termId ? ClassOffering::nextCode((int) $termId) : 'CL-1001',
        ]);

        return view('classes.form', $this->formData($class, []));
    }

    public function store(Request $request)
    {
        [$data, $meetings] = $this->validated($request);

        $class = DB::transaction(function () use ($data, $meetings) {
            $class = ClassOffering::create($data);
            $class->meetings()->createMany($meetings);

            return $class;
        });

        AuditLogger::log('class.created', $class, "Created class {$class->class_code}", null, $data + ['meetings' => $meetings]);

        return redirect()->route('classes.show', $class)->with('success', 'Class created.');
    }

    public function show(ClassOffering $classOffering)
    {
        Gate::authorize('view', $classOffering);

        $classOffering->load(['subject', 'teacher', 'term.academicYear', 'meetings']);

        $isStaff = in_array(auth()->user()->role, ['admin', 'registrar'], true);

        // Teachers only see students who are officially enrolled.
        $roster = $classOffering->enrollments()
            ->with(['student.program'])
            ->when(! $isStaff, fn ($q) => $q->where('status', Enrollment::STATUS_ENROLLED))
            ->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->last_name . $enrollment->student->first_name);

        return view('classes.show', [
            'class' => $classOffering,
            'roster' => $roster,
            'isStaff' => $isStaff,
            'seatsTaken' => $classOffering->seatsTaken(),
        ]);
    }

    public function edit(ClassOffering $classOffering)
    {
        if ($classOffering->term->isClosed()) {
            return redirect()->route('classes.show', $classOffering)->with('error', 'This class belongs to a closed term and cannot be edited.');
        }

        $classOffering->load('meetings');

        $rows = $classOffering->meetings->sortBy(['day_of_week', 'start_time'])->map(fn ($m) => [
            'day' => $m->day_of_week,
            'start' => substr($m->start_time, 0, 5),
            'end' => substr($m->end_time, 0, 5),
            'room' => $m->room,
        ])->values()->all();

        return view('classes.form', $this->formData($classOffering, $rows));
    }

    public function update(Request $request, ClassOffering $classOffering)
    {
        if ($classOffering->term->isClosed()) {
            return redirect()->route('classes.show', $classOffering)->with('error', 'This class belongs to a closed term and cannot be edited.');
        }

        [$data, $meetings] = $this->validated($request, $classOffering);

        $old = $classOffering->only(['term_id', 'subject_id', 'section', 'class_code', 'teacher_id', 'capacity', 'status']);

        DB::transaction(function () use ($classOffering, $data, $meetings) {
            $classOffering->update($data);
            $classOffering->meetings()->delete();
            $classOffering->meetings()->createMany($meetings);
        });

        AuditLogger::log('class.updated', $classOffering, "Updated class {$classOffering->class_code}", $old, $data + ['meetings' => $meetings]);

        return redirect()->route('classes.show', $classOffering)->with('success', 'Class updated.');
    }

    /** A teacher's own classes. */
    public function mine(Request $request)
    {
        $currentTerm = Term::current();
        $termId = $request->input('term', $currentTerm?->id);

        $classes = ClassOffering::with(['subject', 'term', 'meetings'])
            ->withCount(['enrollments as enrolled_count' => fn ($q) => $q->where('status', Enrollment::STATUS_ENROLLED)])
            ->where('teacher_id', $request->user()->id)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->orderBy('class_code')
            ->get();

        return view('classes.mine', [
            'classes' => $classes,
            'terms' => Term::with('academicYear')->orderByDesc('start_date')->get(),
            'termId' => $termId,
        ]);
    }

    private function formData(ClassOffering $class, array $meetingRows): array
    {
        $rows = [];
        for ($i = 0; $i < self::MEETING_ROWS; $i++) {
            $rows[$i] = $meetingRows[$i] ?? ['day' => null, 'start' => null, 'end' => null, 'room' => null];
        }

        return [
            'class' => $class,
            'meetingRows' => $rows,
            'terms' => Term::with('academicYear')->whereNull('closed_at')->orderByDesc('start_date')->get(),
            'subjects' => Subject::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $class->subject_id))->orderBy('code')->get(),
            'teachers' => User::where('role', 'teacher')
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $class->teacher_id))
                ->orderBy('name')->get(),
            'days' => ClassMeeting::DAYS,
            'hasEnrollments' => $class->exists && $class->enrollments()->exists(),
        ];
    }

    /** @return array{0: array, 1: array} the class fields and the cleaned meeting rows */
    private function validated(Request $request, ?ClassOffering $class = null): array
    {
        $request->merge(['class_code' => strtoupper(trim((string) $request->input('class_code')))]);

        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'section' => ['required', 'string', 'max:20'],
            'class_code' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
            'teacher_id' => ['required', 'exists:users,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::in([ClassOffering::STATUS_OPEN, ClassOffering::STATUS_CLOSED, ClassOffering::STATUS_CANCELLED])],
            'meetings' => ['nullable', 'array', 'max:' . self::MEETING_ROWS],
            'meetings.*.day' => ['nullable', 'integer', 'between:1,7'],
            'meetings.*.start' => ['nullable', 'date_format:H:i'],
            'meetings.*.end' => ['nullable', 'date_format:H:i'],
            'meetings.*.room' => ['nullable', 'string', 'max:50'],
        ], ['class_code.regex' => 'Use letters, numbers and dashes only.']);

        $term = Term::findOrFail($data['term_id']);
        if ($term->isClosed()) {
            $this->fail('term_id', 'This term is closed.');
        }

        // Teacher must be an active teacher (or the one already assigned).
        $teacherOk = User::where('id', $data['teacher_id'])->where('role', 'teacher')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $class?->teacher_id))->exists();
        if (! $teacherOk) {
            $this->fail('teacher_id', 'Choose an active teacher.');
        }

        $subjectOk = Subject::where('id', $data['subject_id'])
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $class?->subject_id))->exists();
        if (! $subjectOk) {
            $this->fail('subject_id', 'Choose an active subject.');
        }

        // Unique code, and one subject/section per term.
        $others = ClassOffering::where('term_id', $term->id)->when($class, fn ($q) => $q->where('id', '!=', $class->id));
        if ((clone $others)->where('class_code', $data['class_code'])->exists()) {
            $this->fail('class_code', 'This class code is already used in the term.');
        }
        if ((clone $others)->where('subject_id', $data['subject_id'])->where('section', $data['section'])->exists()) {
            $this->fail('section', 'This subject already has a class with that section in the term.');
        }

        // Meeting rows: a row counts when a day is chosen.
        $meetings = [];
        foreach ($data['meetings'] ?? [] as $row) {
            if (($row['day'] ?? '') === '' || $row['day'] === null) {
                continue;
            }
            if (empty($row['start']) || empty($row['end'])) {
                $this->fail('meetings', 'Every meeting needs a start and an end time.');
            }
            if ($row['end'] <= $row['start']) {
                $this->fail('meetings', 'A meeting must end after it starts.');
            }
            $room = trim((string) ($row['room'] ?? ''));
            $meetings[] = [
                'day_of_week' => (int) $row['day'],
                'start_time' => $row['start'],
                'end_time' => $row['end'],
                'room' => $room === '' ? null : $room,
            ];
        }
        if ($meetings === []) {
            $this->fail('meetings', 'Add at least one meeting time.');
        }

        if ($class) {
            $this->checkEditRules($class, $data, $meetings);
        }

        if ($data['status'] !== ClassOffering::STATUS_CANCELLED) {
            $conflicts = ScheduleChecker::classConflicts(
                $term->id,
                (int) $data['teacher_id'],
                array_map(fn ($m) => ['day' => $m['day_of_week'], 'start' => $m['start_time'], 'end' => $m['end_time'], 'room' => $m['room']], $meetings),
                $class?->id,
            );
            if ($conflicts !== []) {
                throw ValidationException::withMessages(['meetings' => $conflicts]);
            }
        }

        unset($data['meetings']);

        return [$data, $meetings];
    }

    /** Protect students who are already placed in the class. */
    private function checkEditRules(ClassOffering $class, array $data, array $meetings): void
    {
        if ($class->enrollments()->exists()) {
            if ((int) $data['term_id'] !== (int) $class->term_id) {
                $this->fail('term_id', 'The term cannot change once students have requested this class.');
            }
            if ((int) $data['subject_id'] !== (int) $class->subject_id) {
                $this->fail('subject_id', 'The subject cannot change once students have requested this class.');
            }
        }

        $seats = $class->seatsTaken();
        if ((int) $data['capacity'] < $seats) {
            $this->fail('capacity', "Capacity cannot be lower than the {$seats} seats already taken.");
        }

        if ($data['status'] === ClassOffering::STATUS_CANCELLED
            && $class->enrollments()->whereIn('status', [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])->exists()) {
            $this->fail('status', 'Cancel or reject the pending, approved and enrolled requests before cancelling the class.');
        }

        if ($seats > 0) {
            $signature = fn (array $rows) => collect($rows)
                ->map(fn ($r) => $r['day_of_week'] . '|' . substr($r['start_time'], 0, 5) . '|' . substr($r['end_time'], 0, 5))
                ->sort()->values()->all();

            $current = $class->meetings->map(fn ($m) => [
                'day_of_week' => $m->day_of_week, 'start_time' => $m->start_time, 'end_time' => $m->end_time,
            ])->all();

            if ($signature($current) !== $signature($meetings)) {
                $this->fail('meetings', 'Days and times are locked while students hold seats in this class. Room and teacher can still change.');
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
