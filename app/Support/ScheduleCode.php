<?php

namespace App\Support;

use App\Models\ClassOffering;
use App\Models\Term;

/**
 * Schedule codes, as used at Cavite State University: one number per class in a term.
 *
 *   2026 1 0007  ->  202610007
 *   year term number
 *
 * year   = the year the academic year starts (2026 for 2026-2027)
 * term   = the term's place in its academic year by start date (1st semester = 1, 2nd = 2, summer = 3)
 * number = the next free number in that term, from 0001
 */
final class ScheduleCode
{
    public static function prefix(Term $term): string
    {
        $term->loadMissing('academicYear');

        $start = $term->start_date->toDateString();
        $position = Term::where('academic_year_id', $term->academic_year_id)
            ->where(fn ($q) => $q->whereDate('start_date', '<', $start)
                ->orWhere(fn ($same) => $same->whereDate('start_date', $start)->where('id', '<', $term->id)))
            ->count() + 1;

        return $term->academicYear->start_date->format('Y') . min($position, 9);
    }

    /** The next unused code in the term. Call inside a transaction that locks the term row. */
    public static function next(Term $term): string
    {
        $prefix = self::prefix($term);

        $highest = ClassOffering::where('term_id', $term->id)
            ->where('class_code', 'like', $prefix . '____')
            ->pluck('class_code')
            ->map(fn ($code) => (int) substr($code, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
