<?php

namespace App\Services;

use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Everything the student portal shows, always for one student. Read-only. */
class StudentPortalData
{
    /** Terms in which the student has any class, plus the current term. Newest first. */
    public static function historyTerms(Student $student): Collection
    {
        $ids = DB::table('enrollments as e')
            ->join('class_offerings as c', 'c.id', '=', 'e.class_offering_id')
            ->where('e.student_id', $student->id)
            ->pluck('c.term_id');

        if ($current = Term::current()) {
            $ids->push($current->id);
        }

        return Term::with('academicYear')->whereIn('id', $ids->unique()->all())->orderByDesc('start_date')->get();
    }

    /** The requested term if it is in the list, otherwise the current one, otherwise the newest. */
    public static function pickTerm(Collection $terms, mixed $requested): ?Term
    {
        return $terms->firstWhere('id', (int) $requested)
            ?? $terms->firstWhere('is_current', true)
            ?? $terms->first();
    }

    /** Classes the student is officially enrolled in for a term. */
    public static function enrolledClasses(Student $student, ?Term $term): Collection
    {
        if (! $term) {
            return collect();
        }

        return ClassOffering::with(['subject', 'teacher', 'meetings', 'term.academicYear'])
            ->where('term_id', $term->id)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('student_id', $student->id)
                ->where('status', Enrollment::STATUS_ENROLLED))
            ->orderBy('class_code')
            ->get();
    }

    public static function units(Collection $classes): float
    {
        return (float) $classes->sum(fn ($class) => (float) $class->subject->units);
    }

    /**
     * Attendance per class and overall, using the same policy as the staff pages.
     *
     * @return array{rows: Collection, overall: array, percentage: ?float, flagged: bool, rules: array}
     */
    public static function attendance(Student $student, Collection $classes): array
    {
        $rules = AttendanceStats::rules();
        $raw = [];

        if ($classes->isNotEmpty()) {
            $found = DB::table('attendance_records as r')
                ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
                ->where('r.student_id', $student->id)
                ->whereIn('s.class_offering_id', $classes->pluck('id')->all())
                ->select('s.class_offering_id as class_id', 'r.status', DB::raw('count(*) as total'))
                ->groupBy('s.class_offering_id', 'r.status')
                ->get();

            foreach ($found as $row) {
                $raw[$row->class_id][$row->status] = $row->total;
            }
        }

        $overall = AttendanceStats::counts([]);

        $rows = $classes->map(function (ClassOffering $class) use ($raw, $rules, &$overall) {
            $counts = AttendanceStats::counts($raw[$class->id] ?? []);

            foreach ($counts as $status => $number) {
                $overall[$status] += $number;
            }

            return [
                'class' => $class,
                'counts' => $counts,
                'meetings' => array_sum($counts),
                'percentage' => AttendanceStats::percentage($counts, $rules),
                'flagged' => AttendanceStats::isFlagged($counts, $rules),
            ];
        })->values();

        return [
            'rows' => $rows,
            'overall' => $overall,
            'percentage' => AttendanceStats::percentage($overall, $rules),
            'flagged' => $rows->contains('flagged', true),
            'rules' => $rules,
        ];
    }

    public static function percent(?float $value): string
    {
        return $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    }

    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
