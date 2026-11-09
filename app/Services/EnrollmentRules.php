<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Requirement;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRequirement;
use Illuminate\Support\Collection;

/**
 * The rules a student must pass to ask for, or be approved for, a class.
 * Every check returns readable messages; an empty list means "allowed".
 */
class EnrollmentRules
{
    /**
     * Facts about the student in one term, loaded once and reused for many classes.
     *
     * @return array{holding: Collection, requested: Collection, units: float, maxUnits: float, unverified: string[]}
     */
    public static function context(Student $student, int $termId, ?int $ignoreEnrollmentId = null): array
    {
        $rows = Enrollment::with(['classOffering.subject', 'classOffering.meetings'])
            ->where('student_id', $student->id)
            ->whereIn('status', [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])
            ->when($ignoreEnrollmentId, fn ($q) => $q->where('id', '!=', $ignoreEnrollmentId))
            ->whereHas('classOffering', fn ($q) => $q->where('term_id', $termId))
            ->get();

        $holding = $rows->whereIn('status', [Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])
            ->map(fn ($row) => $row->classOffering)->values();

        $verified = StudentRequirement::where('student_id', $student->id)
            ->where('status', StudentRequirement::STATUS_VERIFIED)
            ->pluck('requirement_id')->all();

        return [
            'holding' => $holding,
            'requested' => $rows->map(fn ($row) => $row->classOffering)->values(),
            'units' => (float) $holding->sum(fn ($class) => (float) $class->subject->units),
            'maxUnits' => (float) Setting::get('max_units_per_term', '24'),
            'unverified' => Requirement::where('is_active', true)->where('is_required', true)
                ->whereNotIn('id', $verified ?: [0])->orderBy('name')->pluck('name')->all(),
        ];
    }

    /** Convenience wrapper that builds its own context. */
    public static function check(Student $student, ClassOffering $class, bool $forApproval = false, ?Enrollment $ignore = null): array
    {
        return self::problems($student, $class, self::context($student, $class->term_id, $ignore?->id), $forApproval);
    }

    /**
     * @param  Collection<int, ClassOffering>|null  $extra  other classes chosen in the same request
     * @return string[]
     */
    public static function problems(Student $student, ClassOffering $class, array $context, bool $forApproval = false, ?Collection $extra = null): array
    {
        $class->loadMissing(['term', 'subject.programs', 'meetings']);
        $extra = ($extra ?? collect())->reject(fn ($other) => $other->id === $class->id)->values();
        $problems = [];

        if ($student->trashed() || $student->status !== Student::STATUS_ACTIVE) {
            $problems[] = 'The student is not active.';
        }

        // Curriculum: the subject must belong to the student's program (general subjects are open to all).
        if (! $class->subject->isOpenTo($student->program_id)) {
            $problems[] = "{$class->subject->code} is a major subject for {$class->subject->programsLabel()} students only.";
        }

        if ($class->term->isClosed()) {
            $problems[] = 'This term is closed and its records are locked.';
        } elseif (! $class->term->enrollment_open) {
            $problems[] = 'Enrollment is not open for this term.';
        }

        if ($class->status !== ClassOffering::STATUS_OPEN) {
            $problems[] = "This class is {$class->status} and takes no new students.";
        }

        if ($class->seatsTaken() >= $class->capacity) {
            $problems[] = 'This class is full.';
        }

        // One class per subject in a term.
        foreach ($context['requested']->merge($extra) as $other) {
            if ($other->id !== $class->id && $other->subject_id === $class->subject_id) {
                $problems[] = "The student already has {$other->class_code} for the same subject.";
                break;
            }
        }

        // No two classes at the same time.
        foreach ($context['holding']->merge($extra) as $other) {
            $other->loadMissing(['meetings', 'subject']);

            foreach ($class->meetings as $mine) {
                foreach ($other->meetings as $theirs) {
                    if (ScheduleChecker::overlap(self::slot($mine), self::slot($theirs))) {
                        $when = ClassMeeting::DAYS[$theirs->day_of_week] . ' ' . ScheduleChecker::time($theirs->start_time) . ' to ' . ScheduleChecker::time($theirs->end_time);
                        $problems[] = "Schedule conflict with {$other->class_code} ({$other->subject->code}) on {$when}.";
                        continue 3;
                    }
                }
            }
        }

        // Unit limit for the term.
        $extraUnits = (float) $extra->sum(fn ($other) => (float) $other->subject->units);
        $total = $context['units'] + $extraUnits + (float) $class->subject->units;
        if ($total > $context['maxUnits']) {
            $problems[] = sprintf(
                'Unit limit exceeded: %s units would be more than the %s allowed per term.',
                rtrim(rtrim(number_format($total, 1), '0'), '.'),
                rtrim(rtrim(number_format($context['maxUnits'], 1), '0'), '.'),
            );
        }

        if ($forApproval && $context['unverified'] !== []) {
            $problems[] = 'Required documents are not verified: ' . implode(', ', $context['unverified']) . '.';
        }

        return array_values(array_unique($problems));
    }

    private static function slot(ClassMeeting $meeting): array
    {
        return [
            'day' => $meeting->day_of_week,
            'start' => substr($meeting->start_time, 0, 5),
            'end' => substr($meeting->end_time, 0, 5),
        ];
    }
}
