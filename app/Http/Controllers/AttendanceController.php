<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceException;
use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Services\AttendanceService;
use App\Services\AttendanceStats;
use App\Notifications\Notice;
use App\Support\Notify;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    /** Daily / weekly / monthly attendance dashboard and the list of sheets. */
    public function index(Request $request)
    {
        $user = $request->user();
        $isTeacher = $user->role === 'teacher';

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'range' => ['nullable', Rule::in(['today', 'week', 'month'])],
        ]);

        $term = $request->filled('term') ? Term::with('academicYear')->find($request->input('term')) : Term::with('academicYear')->where('is_current', true)->first();
        [$from, $to, $range] = $this->range($request);

        $classes = ClassOffering::with('subject')
            ->when($term, fn ($q) => $q->where('term_id', $term->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($isTeacher, fn ($q) => $q->where('teacher_id', $user->id))
            ->orderBy('class_code')->get();

        $classId = $classes->contains('id', (int) $request->input('class_offering')) ? (int) $request->input('class_offering') : null;

        // Totals for the chosen days.
        $totals = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('class_offerings as c', 'c.id', '=', 's.class_offering_id')
            ->whereDate('s.session_date', '>=', $from->toDateString())
            ->whereDate('s.session_date', '<=', $to->toDateString())
            ->when($term, fn ($q) => $q->where('c.term_id', $term->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($isTeacher, fn ($q) => $q->where('c.teacher_id', $user->id))
            ->when($classId, fn ($q) => $q->where('c.id', $classId))
            ->selectRaw('r.status as status, count(*) as total')
            ->groupBy('r.status')->pluck('total', 'status')->all();

        $counts = AttendanceStats::counts($totals);

        $sessions = AttendanceSession::with(['classOffering.subject', 'classOffering.teacher'])
            ->withCount([
                'records as present_count' => fn ($q) => $q->where('status', AttendanceRecord::PRESENT),
                'records as late_count' => fn ($q) => $q->where('status', AttendanceRecord::LATE),
                'records as absent_count' => fn ($q) => $q->where('status', AttendanceRecord::ABSENT),
                'records as excused_count' => fn ($q) => $q->where('status', AttendanceRecord::EXCUSED),
            ])
            ->whereDate('session_date', '>=', $from->toDateString())
            ->whereDate('session_date', '<=', $to->toDateString())
            ->whereHas('classOffering', fn ($q) => $q
                ->when($term, fn ($t) => $t->where('term_id', $term->id), fn ($t) => $t->whereRaw('1 = 0'))
                ->when($isTeacher, fn ($t) => $t->where('teacher_id', $user->id))
                ->when($classId, fn ($t) => $t->where('id', $classId)))
            ->orderByDesc('session_date')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('attendance.index', [
            'term' => $term,
            'terms' => Term::with('academicYear')->orderByDesc('start_date')->get(),
            'classes' => $classes,
            'classId' => $classId,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'counts' => $counts,
            'percentage' => AttendanceStats::percentage($counts),
            'sessions' => $sessions,
            'flagged' => $term ? AttendanceStats::flagged($term, $isTeacher ? $user->id : null, $classId) : collect(),
            'threshold' => AttendanceStats::rules()['threshold'],
            'isStaff' => ! $isTeacher,
        ]);
    }

    /** One class on one date: view it, or fill it in when the user may. */
    public function sheet(Request $request)
    {
        $request->validate([
            'class_offering' => ['required', 'integer', 'exists:class_offerings,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $class = ClassOffering::with(['subject', 'teacher', 'term', 'meetings'])->findOrFail($request->input('class_offering'));
        Gate::authorize('view', $class);

        $classDays = AttendanceService::classDays($class);

        if (! $request->filled('date')) {
            $date = $classDays[0]['date'] ?? today();

            return redirect()->route('attendance.sheet', ['class_offering' => $class->id, 'date' => $date->toDateString()]);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->input('date'))->startOfDay();
        $session = AttendanceSession::with(['records', 'takenBy'])->where('class_offering_id', $class->id)->whereDate('session_date', $date)->first();
        $records = $session ? $session->records->keyBy('student_id') : collect();

        return view('attendance.sheet', [
            'class' => $class,
            'date' => $date,
            'session' => $session,
            'records' => $records,
            'students' => AttendanceService::eligible($class, $date, $records->keys()->all()),
            'excuses' => AbsenceExcuse::where('class_offering_id', $class->id)->whereDate('date', $date)->get()->keyBy('student_id'),
            'access' => AttendanceService::access($request->user(), $class, $date),
            'classDays' => $classDays,
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'class_offering' => ['required', 'integer', 'exists:class_offerings,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'rows.required' => 'There are no students on this sheet.',
            'rows.min' => 'There are no students on this sheet.',
            'rows.*.status.required' => 'Choose a status for every student.',
            'rows.*.status.in' => 'Choose Present, Absent, Late or Excused for every student.',
        ]);

        $class = ClassOffering::with(['term', 'meetings'])->findOrFail($data['class_offering']);
        Gate::authorize('view', $class);

        $user = $request->user();
        abort_if($user->role === 'registrar', 403);

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $access = AttendanceService::access($user, $class, $date);

        if ($access['mode'] !== 'edit') {
            return back()->withErrors(['sheet' => $access['messages'] ?: ['You cannot change this sheet.']])->withInput();
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($access['needsReason'] && mb_strlen($reason) < 5) {
            return back()->withErrors(['reason' => 'This date is outside the teacher edit window. Give a reason of at least 5 characters.'])->withInput();
        }

        // Who is over the absence limit in this class before the save, to spot who has just gone over.
        $overBefore = AttendanceStats::flagged($class->term, null, $class->id, 1000)->pluck('student.id')->all();

        try {
            $result = AttendanceService::save($user, $class, $date, $data['rows'], $reason ?: null);
        } catch (AttendanceException $e) {
            return back()->withErrors(['sheet' => $e->messages])->withInput();
        }

        // Excuse letters for this day: Excused accepts them, any other status declines them.
        AbsenceExcuse::settle($class, $date, $user);
        $this->alertNewAbsences($class, $overBefore);

        $message = $result['new']
            ? 'Attendance saved.'
            : ($result['changes'] > 0 ? "Attendance updated ({$result['changes']} change" . ($result['changes'] === 1 ? '' : 's') . ').' : 'No changes were made.');

        return redirect()->route('attendance.sheet', ['class_offering' => $class->id, 'date' => $date->toDateString()])->with('success', $message);
    }

    /** Email students who have just gone over the absence limit in this class, and their guardians. */
    private function alertNewAbsences(ClassOffering $class, array $overBefore): void
    {
        $rules = AttendanceStats::rules();
        $class->loadMissing('subject');

        AttendanceStats::flagged($class->term, null, $class->id, 1000)
            ->reject(fn ($row) => in_array($row['student']->id, $overBefore, true))
            ->each(function ($row) use ($class, $rules) {
                $student = $row['student'];
                $absent = $row['counts']['absent'];
                $lines = [
                    "{$student->first_name} has been absent in {$absent} meetings of {$class->subject->code} · {$class->subject->title}, over the school's {$rules['threshold']}% limit.",
                    'Please talk to the teacher or the registrar\'s office. If an absence had a reason, an excuse letter can be sent from the student portal.',
                ];
                Notify::student($student, new Notice("Attendance alert: {$class->subject->code}", $lines, 'View my attendance', route('student.attendance.show', $class->id)));
                Notify::guardians($student, new Notice("Attendance alert for {$student->first_name} {$student->last_name}", $lines, null, null, 'Good day,'));
            });
    }

    /** Per-student attendance for one class. */
    public function summary(Request $request, ClassOffering $classOffering)
    {
        Gate::authorize('view', $classOffering);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $classOffering->load(['subject', 'teacher', 'term.academicYear', 'meetings']);

        $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', $request->input('from'))->startOfDay() : $classOffering->term->start_date->copy();
        $to = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', $request->input('to'))->startOfDay() : today();

        $raw = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->where('s.class_offering_id', $classOffering->id)
            ->whereDate('s.session_date', '>=', $from->toDateString())
            ->whereDate('s.session_date', '<=', $to->toDateString())
            ->select('r.student_id', 'r.status', DB::raw('count(*) as total'))
            ->groupBy('r.student_id', 'r.status')->get()
            ->groupBy('student_id')->map(fn ($rows) => $rows->pluck('total', 'status')->all());

        $enrolledIds = Enrollment::where('class_offering_id', $classOffering->id)->where('status', Enrollment::STATUS_ENROLLED)->pluck('student_id');
        $students = Student::withTrashed()->with('program')->whereIn('id', $enrolledIds->merge($raw->keys())->unique())
            ->orderBy('last_name')->orderBy('first_name')->get();

        $rules = AttendanceStats::rules();

        $rows = $students->map(function ($student) use ($raw, $rules) {
            $counts = AttendanceStats::counts($raw->get($student->id, []));

            return [
                'student' => $student,
                'counts' => $counts,
                'meetings' => array_sum($counts),
                'percentage' => AttendanceStats::percentage($counts, $rules),
                'flagged' => AttendanceStats::isFlagged($counts, $rules),
            ];
        });

        return view('attendance.summary', [
            'class' => $classOffering,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'rules' => $rules,
            'classDays' => AttendanceService::classDays($classOffering, 6),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function range(Request $request): array
    {
        $range = $request->input('range');

        if ($range === 'week') {
            return [today()->startOfWeek(), today()->endOfWeek()->startOfDay(), 'week'];
        }
        if ($range === 'month') {
            return [today()->startOfMonth(), today()->endOfMonth()->startOfDay(), 'month'];
        }

        if ($request->filled('from') || $request->filled('to')) {
            $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', $request->input('from'))->startOfDay() : today();
            $to = $request->filled('to') ? Carbon::createFromFormat('Y-m-d', $request->input('to'))->startOfDay() : $from->copy();

            return $to->lt($from) ? [$to, $from, 'custom'] : [$from, $to, 'custom'];
        }

        return [today(), today(), 'today'];
    }
}
