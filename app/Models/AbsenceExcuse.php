<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's explanation for an absence or late arrival (an excuse letter), for one class meeting.
 * The teacher decides it on that day's sheet: saved as Excused = accepted, anything else = declined.
 */
class AbsenceExcuse extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = ['student_id', 'class_offering_id', 'date', 'reason', 'file_path', 'file_name', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function classOffering(): BelongsTo
    {
        return $this->belongsTo(ClassOffering::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The student's absences and late arrivals, as [date => [class id => subject code]], in classes they are
     * enrolled in that still accept a letter (none decided yet).
     */
    public static function missedByDay(Student $student): array
    {
        $records = AttendanceRecord::with('session.classOffering.subject')
            ->where('student_id', $student->id)
            ->whereIn('status', [AttendanceRecord::ABSENT, AttendanceRecord::LATE])
            ->whereHas('session.classOffering.enrollments', fn ($q) => $q->where('student_id', $student->id)->where('status', Enrollment::STATUS_ENROLLED))
            ->get();

        $decided = self::where('student_id', $student->id)->where('status', '!=', self::STATUS_PENDING)->get()
            ->map(fn (self $e) => $e->class_offering_id . '|' . $e->date->toDateString())->flip();

        $days = [];
        foreach ($records as $record) {
            $class = $record->session->classOffering;
            $date = $record->session->session_date->toDateString();
            if (! isset($decided[$class->id . '|' . $date])) {
                $days[$date][$class->id] = $class->subject->code;
            }
        }

        return $days;
    }

    /** For one class page: [date => "CS102, MATH101"] of the other classes missed that day. */
    public static function otherMissed(Student $student, ClassOffering $class): array
    {
        $others = [];
        foreach (self::missedByDay($student) as $date => $classes) {
            unset($classes[$class->id]);
            if ($classes !== []) {
                $others[$date] = implode(', ', $classes);
            }
        }

        return $others;
    }

    /** After a sheet is saved: each waiting letter for that day is accepted (Excused) or declined. */
    public static function settle(ClassOffering $class, Carbon $date, User $by): void
    {
        $statuses = AttendanceRecord::whereHas('session', fn ($q) => $q->where('class_offering_id', $class->id)->whereDate('session_date', $date))
            ->pluck('status', 'student_id');

        self::where('class_offering_id', $class->id)->whereDate('date', $date)->where('status', self::STATUS_PENDING)->get()
            ->each(function (self $excuse) use ($statuses, $by) {
                if (! isset($statuses[$excuse->student_id])) {
                    return;
                }
                $excuse->update([
                    'status' => $statuses[$excuse->student_id] === AttendanceRecord::EXCUSED ? self::STATUS_ACCEPTED : self::STATUS_DECLINED,
                    'reviewed_by' => $by->id,
                    'reviewed_at' => now(),
                ]);
            });
    }
}
