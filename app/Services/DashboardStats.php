<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\NonClassDay;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Numbers and chart data for the dashboards. Everything comes from the database. */
class DashboardStats
{
    /** Cards for the administrator and the registrar. */
    public static function overview(?Term $term): array
    {
        $inTerm = fn ($q) => $q->where('term_id', $term?->id);

        return [
            'students' => Student::count(),
            'enrolled' => $term ? Enrollment::where('status', Enrollment::STATUS_ENROLLED)->whereHas('classOffering', $inTerm)->distinct()->count('student_id') : 0,
            'pending' => $term ? Enrollment::where('status', Enrollment::STATUS_PENDING)->whereHas('classOffering', $inTerm)->count() : 0,
            'teachers' => User::where('role', 'teacher')->where('is_active', true)->count(),
            'classes' => $term ? ClassOffering::where('term_id', $term->id)->where('status', '!=', ClassOffering::STATUS_CANCELLED)->count() : 0,
            'subjects' => $term ? ClassOffering::where('term_id', $term->id)->where('status', '!=', ClassOffering::STATUS_CANCELLED)->distinct()->count('subject_id') : 0,
        ];
    }

    /**
     * Today's attendance: totals, how many classes should have a sheet, and how many do.
     *
     * @return array{counts: array, percentage: ?float, classes: \Illuminate\Support\Collection, expected: int, recorded: int, holiday: ?string, note: ?string}
     */
    public static function today(?Term $term, ?int $teacherId = null): array
    {
        $empty = ['counts' => AttendanceStats::counts([]), 'percentage' => null, 'classes' => collect(), 'expected' => 0, 'recorded' => 0, 'holiday' => null, 'note' => null];

        if (! $term) {
            return array_merge($empty, ['note' => 'There is no current term.']);
        }

        $today = today();
        $holiday = NonClassDay::whereDate('date', $today)->first();
        $inTerm = $today->gte($term->start_date) && $today->lte($term->end_date) && ! $term->isClosed();

        $raw = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('class_offerings as c', 'c.id', '=', 's.class_offering_id')
            ->whereDate('s.session_date', $today->toDateString())
            ->where('c.term_id', $term->id)
            ->when($teacherId, fn ($q) => $q->where('c.teacher_id', $teacherId))
            ->selectRaw('r.status as status, count(*) as total')
            ->groupBy('r.status')->pluck('total', 'status')->all();

        $counts = AttendanceStats::counts($raw);

        $classes = collect();
        $note = null;

        if ($holiday) {
            $note = "No classes today: {$holiday->description}.";
        } elseif (! $inTerm) {
            $note = 'Today is outside the current term.';
        } else {
            $classes = ClassOffering::with(['subject', 'meetings'])
                ->where('term_id', $term->id)
                ->where('status', '!=', ClassOffering::STATUS_CANCELLED)
                ->hasStudents()
                ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
                ->whereHas('meetings', fn ($q) => $q->where('day_of_week', $today->dayOfWeekIso))
                ->orderBy('class_code')->get();

            if ($classes->isEmpty()) {
                $note = 'No classes meet today.';
            }
        }

        $recordedIds = AttendanceSession::whereDate('session_date', $today)
            ->whereIn('class_offering_id', $classes->pluck('id'))
            ->pluck('class_offering_id')->all();

        return [
            'counts' => $counts,
            'percentage' => AttendanceStats::percentage($counts),
            'classes' => $classes->map(fn ($class) => ['class' => $class, 'recorded' => in_array($class->id, $recordedIds, true)]),
            'expected' => $classes->count(),
            'recorded' => count($recordedIds),
            'holiday' => $holiday?->description,
            'note' => $note,
        ];
    }

    /** Status totals for the whole term so far. */
    public static function termCounts(?Term $term, ?int $teacherId = null): array
    {
        if (! $term) {
            return AttendanceStats::counts([]);
        }

        $raw = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('class_offerings as c', 'c.id', '=', 's.class_offering_id')
            ->where('c.term_id', $term->id)
            ->whereDate('s.session_date', '<=', today()->toDateString())
            ->when($teacherId, fn ($q) => $q->where('c.teacher_id', $teacherId))
            ->selectRaw('r.status as status, count(*) as total')
            ->groupBy('r.status')->pluck('total', 'status')->all();

        return AttendanceStats::counts($raw);
    }

    /**
     * Attendance percentage per week for the term (a null point means no sheets that week).
     *
     * @return array{labels: string[], values: array<int, ?float>, hasData: bool}
     */
    public static function attendanceTrend(?Term $term, ?int $teacherId = null, int $weeks = 12, ?int $classId = null): array
    {
        $none = ['labels' => [], 'values' => [], 'hasData' => false];

        if (! $term) {
            return $none;
        }

        $end = min(today(), $term->end_date->copy());
        $lastMonday = $end->copy()->startOfWeek();
        $firstMonday = $term->start_date->copy()->startOfWeek();

        $mondays = [];
        for ($monday = $lastMonday->copy(); $monday->gte($firstMonday) && count($mondays) < $weeks; $monday->subWeek()) {
            $mondays[] = $monday->copy();
        }
        $mondays = array_reverse($mondays);

        if ($mondays === []) {
            return $none;
        }

        $rows = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('class_offerings as c', 'c.id', '=', 's.class_offering_id')
            ->where('c.term_id', $term->id)
            ->whereDate('s.session_date', '>=', $mondays[0]->toDateString())
            ->whereDate('s.session_date', '<=', $end->toDateString())
            ->when($teacherId, fn ($q) => $q->where('c.teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('c.id', $classId))
            ->select('s.session_date', 'r.status', DB::raw('count(*) as total'))
            ->groupBy('s.session_date', 'r.status')
            ->get();

        $byWeek = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->session_date)->startOfWeek()->toDateString();
            $byWeek[$key][$row->status] = ($byWeek[$key][$row->status] ?? 0) + $row->total;
        }

        $rules = AttendanceStats::rules();
        $values = array_map(function (Carbon $monday) use ($byWeek, $rules) {
            $counts = $byWeek[$monday->toDateString()] ?? null;

            return $counts ? AttendanceStats::percentage(AttendanceStats::counts($counts), $rules) : null;
        }, $mondays);

        return [
            'labels' => array_map(fn (Carbon $monday) => $monday->format('M j'), $mondays),
            'values' => $values,
            // One point is not a trend: wait for two weeks with sheets.
            'hasData' => count(array_filter($values, fn ($value) => $value !== null)) >= 2,
        ];
    }

    /**
     * Requests and enrolled students per month for the term: from its first request (enrollment may open
     * before classes start) or its start, to its end.
     *
     * @return array{labels: string[], requests: int[], enrolled: int[], hasData: bool}
     */
    public static function enrollmentTrend(?Term $term): array
    {
        if (! $term) {
            return ['labels' => [], 'requests' => [], 'enrolled' => [], 'hasData' => false];
        }

        $rows = Enrollment::whereHas('classOffering', fn ($q) => $q->where('term_id', $term->id))
            ->get(['requested_at', 'enrolled_at', 'status']);

        $requests = [];
        $enrolled = [];
        $first = $term->start_date->copy()->startOfMonth();
        foreach ($rows as $row) {
            if ($row->requested_at) {
                $key = $row->requested_at->format('Y-m');
                $requests[$key] = ($requests[$key] ?? 0) + 1;
                $first = $first->min($row->requested_at->copy()->startOfMonth());
            }
            if ($row->status === Enrollment::STATUS_ENROLLED && $row->enrolled_at) {
                $key = $row->enrolled_at->format('Y-m');
                $enrolled[$key] = ($enrolled[$key] ?? 0) + 1;
                $first = $first->min($row->enrolled_at->copy()->startOfMonth());
            }
        }

        $labels = [];
        $requestValues = [];
        $enrolledValues = [];
        $month = $first->copy();
        $last = $term->end_date->copy()->startOfMonth();

        for ($i = 0; $i < 12 && $month->lte($last); $i++, $month->addMonth()) {
            $key = $month->format('Y-m');
            $labels[] = $month->format('M');
            $requestValues[] = $requests[$key] ?? 0;
            $enrolledValues[] = $enrolled[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'requests' => $requestValues,
            'enrolled' => $enrolledValues,
            'hasData' => array_sum($requestValues) + array_sum($enrolledValues) > 0,
        ];
    }

    /**
     * A teacher's next meeting from now, within the next two weeks of the term (skipping non-class days).
     *
     * @return array{class: ClassOffering, start: Carbon, room: ?string}|null
     */
    public static function nextClass(?Term $term, int $teacherId): ?array
    {
        if (! $term) {
            return null;
        }

        $classes = ClassOffering::with(['subject', 'meetings'])
            ->where('term_id', $term->id)->where('teacher_id', $teacherId)
            ->where('status', '!=', ClassOffering::STATUS_CANCELLED)->get();
        if ($classes->isEmpty()) {
            return null;
        }

        $now = now();
        $from = $now->copy()->startOfDay()->max($term->start_date->copy()->startOfDay());
        $holidays = NonClassDay::whereBetween('date', [$from->toDateString(), $from->copy()->addDays(14)->toDateString()])
            ->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();

        for ($day = $from->copy(); $day->lte($from->copy()->addDays(14)) && $day->lte($term->end_date); $day->addDay()) {
            if (in_array($day->toDateString(), $holidays, true)) {
                continue;
            }

            $next = $classes
                ->flatMap(fn (ClassOffering $class) => $class->meetings->where('day_of_week', $day->dayOfWeekIso)
                    ->map(fn ($meeting) => ['class' => $class, 'start' => $day->copy()->setTimeFromTimeString($meeting->start_time), 'room' => $meeting->room]))
                ->filter(fn ($item) => $item['start']->gt($now))
                ->sortBy(fn ($item) => $item['start'])
                ->first();

            if ($next) {
                return $next;
            }
        }

        return null;
    }

    /**
     * Attendance rate per class this term (one teacher's classes, or all), lowest first.
     *
     * @return array{labels: string[], values: array<int, ?float>, hasData: bool}
     */
    public static function attendanceByClass(?Term $term, ?int $teacherId = null): array
    {
        if (! $term) {
            return ['labels' => [], 'values' => [], 'hasData' => false];
        }

        $rows = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('class_offerings as c', 'c.id', '=', 's.class_offering_id')
            ->where('c.term_id', $term->id)
            ->when($teacherId, fn ($q) => $q->where('c.teacher_id', $teacherId))
            ->select('c.id', 'r.status', DB::raw('count(*) as total'))
            ->groupBy('c.id', 'r.status')
            ->get();

        $raw = [];
        foreach ($rows as $row) {
            $raw[$row->id][$row->status] = $row->total;
        }

        $rules = AttendanceStats::rules();
        $classes = ClassOffering::with('subject')->whereIn('id', array_keys($raw))->get()
            ->map(fn (ClassOffering $class) => [
                'label' => $class->subject->code . ' · ' . $class->class_code,
                'value' => AttendanceStats::percentage(AttendanceStats::counts($raw[$class->id]), $rules),
            ])
            ->sortBy('value')->values();

        return [
            'labels' => $classes->pluck('label')->all(),
            'values' => $classes->pluck('value')->all(),
            'hasData' => $classes->isNotEmpty(),
        ];
    }

    /**
     * Sign-in accounts, for the administrator.
     *
     * @return array{active: int, staff: int, students: int, inactive: int}
     */
    public static function accounts(): array
    {
        $active = User::where('is_active', true);

        return [
            'active' => (clone $active)->count(),
            'staff' => (clone $active)->whereIn('role', ['admin', 'registrar', 'teacher'])->count(),
            'students' => (clone $active)->where('role', 'student')->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];
    }
}
