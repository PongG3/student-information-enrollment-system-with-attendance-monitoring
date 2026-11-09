<?php

namespace App\Models;

use App\Support\RoleLabel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // created_at is set by the application; rows are never updated.
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_role', 'action', 'subject_type', 'subject_id',
        'description', 'old_values', 'new_values', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The audit trail is append-only: block edits and deletes.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /** "Teacher", "Registrar"... the role at the time; older rows fall back to the user's current role. */
    protected function roleLabel(): Attribute
    {
        return Attribute::get(fn () => RoleLabel::for($this->user_role ?? $this->user?->role));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
