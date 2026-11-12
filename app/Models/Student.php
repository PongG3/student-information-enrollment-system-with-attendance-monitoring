<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    // Students are archived (soft-deleted), never removed, to keep academic history.
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_GRADUATED = 'graduated';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const TYPE_NEW = 'new';
    public const TYPE_RETURNING = 'returning';
    public const TYPE_TRANSFEREE = 'transferee';

    protected $fillable = [
        'user_id', 'student_number', 'first_name', 'middle_name', 'last_name', 'suffix',
        'gender', 'birth_date', 'address', 'contact_number', 'email',
        'program_id', 'year_level', 'section', 'student_type', 'previous_school', 'photo_path', 'status', 'privacy_consent_at',
        'pending_photo_path', 'pending_photo_at',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'privacy_consent_at' => 'datetime', 'pending_photo_at' => 'datetime'];
    }

    /** "Last, First Middle Suffix" */
    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            $first = trim($this->first_name . ' ' . ($this->middle_name ?? ''));
            $name = $this->last_name . ', ' . $first;

            return $this->suffix ? $name . ' ' . $this->suffix : $name;
        });
    }

    /** Block section as people say it, e.g. "BSIT 1-A"; null when no section is set. */
    protected function blockSection(): Attribute
    {
        return Attribute::get(fn () => $this->section
            ? trim(($this->program?->code ?? '') . ' ' . $this->year_level . '-' . $this->section)
            : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function studentRequirements(): HasMany
    {
        return $this->hasMany(StudentRequirement::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
