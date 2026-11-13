<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\NonClassDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Class meetings that have passed without an attendance sheet.
 *
 * A meeting is "missing" when the class meets that weekday, the day is inside the term and before today,
 * it is not a no-class day (holiday, suspension), the class is not cancelled, and no sheet was saved.
 * Today is left out: the class may not have met yet (today's sheets are counted separately).
 */
class MissingSheets
{
    /**
     * @param  Collection<int, ClassOffering>  $classes  with term and meetings loaded
     * @return Collection<int, array{class: ClassOffering, date: Carbon}> oldest first
     */
    public static function find(Collection $classes, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $classes = $classes->filter(fn (ClassOffering $class) => $class->status !== ClassOffering::STATUS_CANCELLED)->values();
        // A class nobody is enrolled in has no sheet to take.
        $withStudents = ClassOffering::whereIn('id', $classes->pluck('id'))->hasStudents()->pluck('id')->flip();
        $classes = $classes->filter(fn (ClassOffering $class) => isset($withStudents[$class->id]))->values();
        if ($classes->isEmpty()) {
            return collect();
        }

        $yesterday = today()->subDay();
        $to = ($to ?? $yesterday)->copy()->min($yesterday);
        $earliest = $classes->min(fn (ClassOffering $class) => $class->term->start_date);
        $from = ($from ?? $earliest)->copy()->max($earliest);

        if ($from->gt($to)) {
            return collect();
        }

        $noClass = NonClassDay::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->flip();

        $taken = AttendanceSession::whereIn('class_offering_id', $classes->pluck('id'))
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->get(['class_offering_id', 'session_date'])
            ->map(fn ($s) => $s->class_offering_id . '|' . Carbon::parse($s->session_date)->toDateString())
            ->flip();

        $missing = collect();
        foreach ($classes as $class) {
            $days = $class->meetings->pluck('day_of_week')->unique()->all();
            $start = $from->copy()->max($class->term->start_date);
            $end = $to->copy()->min($class->term->end_date);

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $date = $day->toDateString();
                if (in_array($day->dayOfWeekIso, $days, true) && ! isset($noClass[$date]) && ! isset($taken[$class->id . '|' . $date])) {
                    $missing->push(['class' => $class, 'date' => $day->copy()]);
                }
            }
        }

        return $missing->sortBy(fn ($row) => $row['date']->toDateString() . $row['class']->class_code)->values();
    }

    /** Missing sheets in the last two weeks, for one teacher's classes or all of a term's. */
    public static function recentCount(int $termId, ?int $teacherId = null, int $days = 14): int
    {
        $classes = ClassOffering::with(['term', 'meetings'])
            ->where('term_id', $termId)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->get();

        return self::find($classes, today()->subDays($days))->count();
    }
}
