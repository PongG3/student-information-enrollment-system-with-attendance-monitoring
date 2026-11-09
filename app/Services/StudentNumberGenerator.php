<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class StudentNumberGenerator
{
    /**
     * Next student number such as 2026-0007.
     * Safe when two registrars save at the same moment: the yearly counter row is locked,
     * and the unique index on students.student_number is the final guard.
     */
    public static function next(?int $year = null): string
    {
        $year ??= now()->year;

        return DB::transaction(function () use ($year) {
            $row = DB::table('student_number_sequences')->where('year', $year)->lockForUpdate()->first();

            if (! $row) {
                // Start after any number already issued for this year (for example sample data).
                $highest = DB::table('students')->where('student_number', 'like', $year . '-%')->max('student_number');
                $start = $highest ? (int) substr($highest, strlen((string) $year) + 1) : 0;

                try {
                    DB::table('student_number_sequences')->insert(['year' => $year, 'last_number' => $start]);
                } catch (QueryException) {
                    // Another request created the row first.
                }

                $row = DB::table('student_number_sequences')->where('year', $year)->lockForUpdate()->first();
            }

            do {
                $next = $row->last_number + 1;
                $row->last_number = $next;
                $number = sprintf('%d-%04d', $year, $next);
            } while (DB::table('students')->where('student_number', $number)->exists());

            DB::table('student_number_sequences')->where('year', $year)->update(['last_number' => $next]);

            return $number;
        });
    }
}
