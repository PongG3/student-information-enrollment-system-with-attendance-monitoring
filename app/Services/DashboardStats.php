<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\NonClassDay;
use App\Models\Program;
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
    public static function attendanceTrend(?Term $term, ?int $teacherId = null, int $weeks = 12): array
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
            'hasData' => count(array_filter($values, fn ($value) => $value !== null)) > 0,
        ];
    }

    /**
     * Requests and enrolled students per month across the academic year.
     *
     * @return array{labels: string[], requests: int[], enrolled: int[], hasData: bool}
     */
    public static function enrollmentTrend(?Term $term): array
    {
        $year = $term?->academicYear ?? AcademicYear::where('is_current', true)->first();

        if (! $year) {
            return ['labels' => [], 'requests' => [], 'enrolled' => [], 'hasData' => false];
        }

        $rows = Enrollment::whereHas('classOffering.term', fn ($q) => $q->where('academic_year_id', $year->id))
            ->get(['requested_at', 'enrolled_at', 'status']);

        $requests = [];
        $enrolled = [];
        foreach ($rows as $row) {
            if ($row->requested_at) {
                $key = $row->requested_at->format('Y-m');
                $requests[$key] = ($requests[$key] ?? 0) + 1;
            }
            if ($row->status === Enrollment::STATUS_ENROLLED && $row->enrolled_at) {
                $key = $row->enrolled_at->format('Y-m');
                $enrolled[$key] = ($enrolled[$key] ?? 0) + 1;
            }
        }

        $labels = [];
        $requestValues = [];
        $enrolledValues = [];
        $month = $year->start_date->copy()->startOfMonth();
        $last = $year->end_date->copy()->startOfMonth();

        for ($i = 0; $i < 14 && $month->lte($last); $i++, $month->addMonth()) {
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

    /** Requests by status in the term. */
    public static function enrollmentStatus(?Term $term): array
    {
        $raw = $term
            ? Enrollment::whereHas('classOffering', fn ($q) => $q->where('term_id', $term->id))
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all()
            : [];

        $order = [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED, Enrollment::STATUS_REJECTED, Enrollment::STATUS_CANCELLED];

        return [
            'labels' => array_map('ucfirst', $order),
            'values' => array_map(fn ($status) => (int) ($raw[$status] ?? 0), $order),
            'hasData' => array_sum($raw) > 0,
        ];
    }

    /** Active students per active program. */
    public static function byProgram(): array
    {
        $programs = Program::where('is_active', true)
            ->withCount(['students as active_students' => fn ($q) => $q->where('status', Student::STATUS_ACTIVE)])
            ->orderBy('code')->get();

        return [
            'labels' => $programs->pluck('code')->all(),
            'values' => $programs->pluck('active_students')->map(fn ($n) => (int) $n)->all(),
            'hasData' => $programs->sum('active_students') > 0,
        ];
    }
}
