<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Term;
use App\Models\User;

/** Small counts shown on menu items, so people can see what is waiting without opening the dashboard. */
class MenuBadges
{
    /** @return array<string, int> */
    public static function for(User $user): array
    {
        if (! in_array($user->role, ['admin', 'registrar'], true)) {
            return [];
        }

        $termId = Term::where('is_current', true)->value('id');

        return [
            'pending_enrollments' => $termId
                ? Enrollment::where('status', Enrollment::STATUS_PENDING)->whereHas('classOffering', fn ($q) => $q->where('term_id', $termId))->count()
                : 0,
        ];
    }
}
