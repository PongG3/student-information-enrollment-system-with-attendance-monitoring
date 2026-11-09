<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    /** Fields that must never be written to the log. */
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret'];

    public static function log(
        string $action,
        ?Model $subject,
        string $description,
        ?array $old = null,
        ?array $new = null,
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'user_role' => auth()->user()?->role,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'old_values' => self::clean($old),
            'new_values' => self::clean($new),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private static function clean(?array $values): ?array
    {
        return $values === null ? null : array_diff_key($values, array_flip(self::HIDDEN));
    }
}
