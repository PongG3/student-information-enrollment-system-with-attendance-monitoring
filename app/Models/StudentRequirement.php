<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRequirement extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'student_id', 'requirement_id', 'status', 'submitted_at',
        'verified_by', 'verified_at', 'remarks', 'file_path', 'file_name',
        'returned_reason', 'returned_at', 'returned_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** Sent back by the registrar and not handed in again yet: the student sees why. */
    public function isReturned(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->returned_at !== null;
    }
}
