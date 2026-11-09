<?php

namespace App\Models;

use App\Services\ScheduleChecker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassOffering extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    private const DAY_ABBREVIATIONS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    protected $fillable = [
        'term_id', 'subject_id', 'section', 'class_code',
        'teacher_id', 'capacity', 'status',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(ClassMeeting::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /** Seats held by approved or enrolled students. */
    public function seatsTaken(): int
    {
        return $this->enrollments()
            ->whereIn('status', [Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])
            ->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seatsTaken());
    }

    /** Suggested next class code for a term, such as CL-1007. */
    public static function nextCode(int $termId): string
    {
        $highest = 1000;

        foreach (static::where('term_id', $termId)->pluck('class_code') as $code) {
            if (preg_match('/^CL-(\d+)$/', $code, $match)) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        return 'CL-' . ($highest + 1);
    }

    /** "Mon, Wed 8:00 AM to 9:30 AM · Room 101" (needs the meetings relation loaded). */
    public function scheduleSummary(): string
    {
        $groups = [];

        foreach ($this->meetings->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']]) as $meeting) {
            $key = $meeting->start_time . '|' . $meeting->end_time . '|' . ($meeting->room ?? '');
            $groups[$key]['days'][] = self::DAY_ABBREVIATIONS[$meeting->day_of_week];
            $groups[$key]['meeting'] = $meeting;
        }

        return collect($groups)->map(function ($group) {
            $meeting = $group['meeting'];
            $text = implode(', ', $group['days']) . ' '
                . ScheduleChecker::time($meeting->start_time) . ' to ' . ScheduleChecker::time($meeting->end_time);

            return $meeting->room ? $text . ' · ' . $meeting->room : $text;
        })->implode('; ');
    }
}
