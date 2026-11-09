<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;

/** "What changed in the last 7 days" for the dashboard cards. */
class DashboardWeek
{
    /** @return array{students: int, enrolled: int} */
    public static function changes(?Term $term): array
    {
        $since = now()->subDays(7);

        return [
            'students' => Student::where('created_at', '>=', $since)->count(),
            'enrolled' => $term
                ? Enrollment::where('status', Enrollment::STATUS_ENROLLED)->where('enrolled_at', '>=', $since)
                    ->whereHas('classOffering', fn ($q) => $q->where('term_id', $term->id))->count()
                : 0,
        ];
    }
}
