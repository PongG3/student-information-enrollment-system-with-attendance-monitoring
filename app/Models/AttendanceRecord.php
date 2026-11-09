<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const PRESENT = 'present';
    public const ABSENT = 'absent';
    public const LATE = 'late';
    public const EXCUSED = 'excused';

    public const STATUSES = [self::PRESENT, self::ABSENT, self::LATE, self::EXCUSED];

    protected $fillable = [
        'attendance_session_id', 'student_id', 'status', 'remarks', 'recorded_by', 'updated_by',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
