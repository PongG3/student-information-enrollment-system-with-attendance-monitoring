<?php

namespace App\Services;

use App\Models\ClassOffering;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance percentages and frequent-absence alerts.
 *
 * Policy (changeable in System Settings):
 *  - Present always counts as attended; Late counts as attended when "late counts as attended" is on.
 *  - Excused is either left out of the percentage or counted as attended.
 *  - A student is flagged when absent in more than the threshold share of meetings (at least 3 meetings recorded).
 */
class AttendanceStats
{
    public const MIN_MEETINGS_FOR_ALERT = 3;

    /** @return array{late: bool, excused: string, threshold: int} */
    public static function rules(): array
    {
        return [
            'late' => Setting::get('late_counts_as_attended', '1') === '1',
            'excused' => Setting::get('excused_in_percentage', 'excluded'),
            'threshold' => (int) Setting::get('absentee_threshold_percent', '20'),
        ];
    }

    public static function counts(array $raw): array
    {
        return [
            'present' => (int) ($raw['present'] ?? 0),
            'late' => (int) ($raw['late'] ?? 0),
            'absent' => (int) ($raw['absent'] ?? 0),
            'excused' => (int) ($raw['excused'] ?? 0),
        ];
    }

    /** Meetings that count toward the percentage. */
    public static function counted(array $counts, array $rules): int
    {
        $total = $counts['present'] + $counts['late'] + $counts['absent'] + $counts['excused'];

        return $rules['excused'] === 'excluded' ? $total - $counts['excused'] : $total;
    }

    /** Attendance percentage, or null when nothing counts yet. */
    public static function percentage(array $counts, ?array $rules = null): ?float
    {
        $rules ??= self::rules();
        $counted = self::counted($counts, $rules);

        if ($counted <= 0) {
            return null;
        }

        $attended = $counts['present']
            + ($rules['late'] ? $counts['late'] : 0)
            + ($rules['excused'] === 'attended' ? $counts['excused'] : 0);

        return round($attended / $counted * 100, 1);
    }

    public static function absenceRate(array $counts, ?array $rules = null): ?float
    {
        $rules ??= self::rules();
        $counted = self::counted($counts, $rules);

        return $counted > 0 ? round($counts['absent'] / $counted * 100, 1) : null;
    }

    public static function isFlagged(array $counts, ?array $rules = null): bool
    {
        $rules ??= self::rules();
        $rate = self::absenceRate($counts, $rules);

        return $rate !== null
            && self::counted($counts, $rules) >= self::MIN_MEETINGS_FOR_ALERT
            && $rate > $rules['threshold'];
    }

    /**
     * Students over the absence threshold in a term, worst first.
     *
     * @return Collection<int, array{student: Student, class: ClassOffering, counts: array, absence_rate: float, percentage: ?float}>
     */
    public static function flagged(Term $term, ?int $teacherId = null, ?int $classId = null, int $limit = 10): Collection
    {
        $rules = self::rules();

        $rows = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('class_offerings as c', 'c.id', '=', 's.class_offering_id')
            ->where('c.term_id', $term->id)
            ->when($teacherId, fn ($q) => $q->where('c.teacher_id', $teacherId))
            ->when($classId, fn ($q) => $q->where('c.id', $classId))
            ->select('r.student_id', 'c.id as class_id', 'r.status', DB::raw('count(*) as total'))
            ->groupBy('r.student_id', 'c.id', 'r.status')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->student_id . '|' . $row->class_id][$row->status] = $row->total;
        }

        $flagged = collect($grouped)->map(function ($raw, $key) use ($rules) {
            [$studentId, $classId] = explode('|', $key);
            $counts = self::counts($raw);

            return self::isFlagged($counts, $rules)
                ? ['student_id' => (int) $studentId, 'class_id' => (int) $classId, 'counts' => $counts, 'absence_rate' => self::absenceRate($counts, $rules), 'percentage' => self::percentage($counts, $rules)]
                : null;
        })->filter()->sortByDesc('absence_rate')->take($limit)->values();

        $students = Student::withTrashed()->whereIn('id', $flagged->pluck('student_id'))->get()->keyBy('id');
        $classes = ClassOffering::with('subject')->whereIn('id', $flagged->pluck('class_id'))->get()->keyBy('id');

        return $flagged->map(fn ($row) => [
            'student' => $students[$row['student_id']],
            'class' => $classes[$row['class_id']],
            'counts' => $row['counts'],
            'absence_rate' => $row['absence_rate'],
            'percentage' => $row['percentage'],
        ]);
    }
}
