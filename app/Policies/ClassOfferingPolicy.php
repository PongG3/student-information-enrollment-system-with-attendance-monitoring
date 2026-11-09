<?php

namespace App\Policies;

use App\Models\ClassOffering;
use App\Models\User;

class ClassOfferingPolicy
{
    /** Staff see every class; a teacher sees only their own. */
    public function view(User $user, ClassOffering $classOffering): bool
    {
        if (in_array($user->role, ['admin', 'registrar'], true)) {
            return true;
        }

        return $user->role === 'teacher' && (int) $classOffering->teacher_id === (int) $user->id;
    }
}
