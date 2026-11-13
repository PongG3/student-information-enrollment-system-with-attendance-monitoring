<?php

namespace App\Services;

use App\Exceptions\EnrollmentRuleException;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Notice;
use App\Support\AuditLogger;
use App\Support\Notify;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every status change goes through here, so each one is checked, locked against
 * double-booking, and written to the audit log.
 *
 * Flow: pending -> approved -> enrolled.  Exits: rejected, cancelled.  Reopen: back to pending.
 */
class EnrollmentWorkflow
{
    /**
     * Record pending requests for a student. All-or-nothing: if any class breaks a rule, none are created.
     *
     * @param  int[]  $classIds
     * @return Collection<int, Enrollment>
     */
    public static function request(Student $student, array $classIds, User $by): Collection
    {
        return DB::transaction(function () use ($student, $classIds, $by) {
            $classIds = array_values(array_unique($classIds));

            Student::withTrashed()->whereKey($student->id)->lockForUpdate()->first();   // same order as lock()

            $classes = ClassOffering::with(['term', 'subject', 'meetings'])
                ->whereIn('id', $classIds)->orderBy('id')->lockForUpdate()->get();

            if ($classes->count() !== count($classIds)) {
                throw new EnrollmentRuleException(['One of the selected classes no longer exists.']);
            }
            if ($classes->pluck('term_id')->unique()->count() > 1) {
                throw new EnrollmentRuleException(['Choose classes from one term at a time.']);
            }

            $context = EnrollmentRules::context($student, $classes->first()->term_id);
            $problems = [];

            foreach ($classes as $class) {
                if (Enrollment::where('student_id', $student->id)->where('class_offering_id', $class->id)->exists()) {
                    $problems[] = "{$class->class_code}: this student already has a request for this class.";
                    continue;
                }

                foreach (EnrollmentRules::problems($student, $class, $context, false, $classes) as $problem) {
                    $problems[] = "{$class->class_code}: {$problem}";
                }
            }

            if ($problems !== []) {
                throw new EnrollmentRuleException($problems);
            }

            return $classes->map(function (ClassOffering $class) use ($student, $by) {
                $enrollment = Enrollment::create([
                    'student_id' => $student->id,
                    'class_offering_id' => $class->id,
                    'status' => Enrollment::STATUS_PENDING,
                    'requested_by' => $by->id,
                    'requested_at' => now(),
                ]);

                AuditLogger::log('enrollment.requested', $enrollment,
                    "Recorded a request for {$student->student_number} in {$class->class_code}", null, ['status' => 'pending']);

                return $enrollment;
            });
        });
    }

    public static function approve(Enrollment $enrollment, User $by): void
    {
        DB::transaction(function () use ($enrollment, $by) {
            [$fresh, $class, $student] = self::lock($enrollment);
            self::mustBe($fresh, [Enrollment::STATUS_PENDING], 'Only pending requests can be approved.');

            $problems = EnrollmentRules::check($student, $class, true, $fresh);
            if ($problems !== []) {
                throw new EnrollmentRuleException($problems);
            }

            $fresh->update([
                'status' => Enrollment::STATUS_APPROVED,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'status_reason' => null,
            ]);

            self::log('enrollment.approved', $fresh, $student, $class, Enrollment::STATUS_PENDING);
        });
    }

    public static function reject(Enrollment $enrollment, User $by, string $reason): void
    {
        [$student, $class] = DB::transaction(function () use ($enrollment, $by, $reason) {
            [$fresh, $class, $student] = self::lock($enrollment);
            self::mustBe($fresh, [Enrollment::STATUS_PENDING], 'Only pending requests can be rejected.');
            self::termMustBeOpen($class);

            $fresh->update([
                'status' => Enrollment::STATUS_REJECTED,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'status_reason' => $reason,
            ]);

            self::log('enrollment.rejected', $fresh, $student, $class, Enrollment::STATUS_PENDING, $reason);

            return [$student, $class];
        });

        Notify::student($student, new Notice(
            "Class request not approved: {$class->subject->code}",
            ["Your request for {$class->subject->code} · {$class->subject->title} ({$class->class_code}) was not approved.", "Reason: {$reason}", 'Please see the registrar\'s office if you have questions.'],
            'View my enrollments', route('student.enrollments'),
        ));
    }

    /** Final confirmation: the student joins the class roster and attendance sheet. */
    public static function enroll(Enrollment $enrollment, User $by): void
    {
        [$student, $class] = DB::transaction(function () use ($enrollment, $by) {
            [$fresh, $class, $student] = self::lock($enrollment);
            self::mustBe($fresh, [Enrollment::STATUS_APPROVED], 'Only approved requests can be enrolled.');
            self::termMustBeOpen($class);

            if ($student->trashed() || $student->status !== Student::STATUS_ACTIVE) {
                throw new EnrollmentRuleException(['The student is not active.']);
            }

            $fresh->update([
                'status' => Enrollment::STATUS_ENROLLED,
                'enrolled_at' => now(),
                'dropped_at' => null,
                'reviewed_by' => $fresh->reviewed_by ?? $by->id,
                'reviewed_at' => $fresh->reviewed_at ?? now(),
            ]);

            self::log('enrollment.enrolled', $fresh, $student, $class, Enrollment::STATUS_APPROVED);

            return [$student, $class];
        });

        $class->loadMissing(['subject', 'meetings']);
        Notify::student($student, new Notice(
            "Enrolled: {$class->subject->code}",
            ["You are now enrolled in {$class->subject->code} · {$class->subject->title} ({$class->class_code}).", 'Schedule: ' . ($class->scheduleSummary() ?: 'to be announced') . '.'],
            'View my schedule', route('student.schedule'),
        ));
    }

    public static function cancel(Enrollment $enrollment, User $by, string $reason): void
    {
        DB::transaction(function () use ($enrollment, $by, $reason) {
            [$fresh, $class, $student] = self::lock($enrollment);
            self::mustBe($fresh, [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED], 'This request cannot be cancelled.');
            self::termMustBeOpen($class);

            $old = $fresh->status;
            $fresh->update([
                'status' => Enrollment::STATUS_CANCELLED,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                // An enrolled student keeps the date they joined and gets a drop date, so class days from
                // before they left can still be recorded. Anyone not yet enrolled has neither.
                'enrolled_at' => $old === Enrollment::STATUS_ENROLLED ? $fresh->enrolled_at : null,
                'dropped_at' => $old === Enrollment::STATUS_ENROLLED ? now() : null,
                'status_reason' => $reason,
            ]);

            self::log('enrollment.cancelled', $fresh, $student, $class, $old, $reason);
        });
    }

    /** Put a rejected or cancelled request back to pending, if it would be allowed today. */
    public static function reopen(Enrollment $enrollment, User $by): void
    {
        DB::transaction(function () use ($enrollment, $by) {
            [$fresh, $class, $student] = self::lock($enrollment);
            self::mustBe($fresh, [Enrollment::STATUS_REJECTED, Enrollment::STATUS_CANCELLED], 'Only rejected or cancelled requests can be reopened.');

            $problems = EnrollmentRules::check($student, $class, false, $fresh);
            if ($problems !== []) {
                throw new EnrollmentRuleException($problems);
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => Enrollment::STATUS_PENDING,
                'requested_by' => $by->id,
                'requested_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'enrolled_at' => null,
                'dropped_at' => null,
                'status_reason' => null,
            ]);

            self::log('enrollment.reopened', $fresh, $student, $class, $old);
        });
    }

    /**
     * Lock the student, the class and the request. The class lock stops two people booking the last seat; the
     * student lock stops two approvals for different classes passing the schedule and unit checks together.
     * Always taken in this order (student, then class) so two requests never wait on each other.
     */
    private static function lock(Enrollment $enrollment): array
    {
        $student = Student::withTrashed()->lockForUpdate()->findOrFail($enrollment->student_id);
        $class = ClassOffering::with(['term', 'subject', 'meetings'])->lockForUpdate()->findOrFail($enrollment->class_offering_id);
        $fresh = Enrollment::lockForUpdate()->findOrFail($enrollment->id);

        return [$fresh, $class, $student];
    }

    private static function mustBe(Enrollment $enrollment, array $statuses, string $message): void
    {
        if (! in_array($enrollment->status, $statuses, true)) {
            throw new EnrollmentRuleException([$message . " It is currently {$enrollment->status}."]);
        }
    }

    private static function termMustBeOpen(ClassOffering $class): void
    {
        if ($class->term->isClosed()) {
            throw new EnrollmentRuleException(['This term is closed and its records are locked.']);
        }
    }

    private static function log(string $action, Enrollment $enrollment, Student $student, ClassOffering $class, string $old, ?string $reason = null): void
    {
        $verb = ucfirst(str_replace('enrollment.', '', $action));

        AuditLogger::log(
            $action,
            $enrollment,
            "{$verb}: {$student->student_number} in {$class->class_code}" . ($reason ? " ({$reason})" : ''),
            ['status' => $old],
            array_filter(['status' => $enrollment->status, 'reason' => $reason]),
        );
    }
}
