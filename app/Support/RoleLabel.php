<?php

namespace App\Support;

class RoleLabel
{
    private const LABELS = [
        'admin' => 'Administrator',
        'registrar' => 'Registrar',
        'teacher' => 'Teacher',
        'student' => 'Student',
    ];

    public static function for(?string $role): ?string
    {
        return $role === null ? null : (self::LABELS[$role] ?? ucfirst($role));
    }

    public static function all(): array
    {
        return self::LABELS;
    }
}
