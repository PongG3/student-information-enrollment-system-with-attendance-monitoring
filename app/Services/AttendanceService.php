<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\NonClassDay;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public static function windowDays(): int
    {
        return max(0, (int) Setting::get('attendance_edit_window_days', '7'));
    }

    /** Why attendance cannot be recorded for this class on this date (empty list = fine). */
    public static function dateProblems(ClassOffering $class, Carbon $date): array
    {
        $class->loadMissing(['term', 'meetings']);
        $problems = [];

        if ($class->term->isClosed()) {
            $problems[] = 'This term is closed and its records are locked.';
        }
        if ($class->status === ClassOffering::STATUS_CANCELLED) {
            $problems[] = 'This class is cancelled.';
        }
        if ($date->gt(today())) {
            $problems[] = 'Attendance cannot be recorded for a future date.';
        }
        if ($date->lt($class->term->start_date) || $date->gt($class->term->end_date)) {
            $problems[] = 'This date is outside the term (' . $class->term->start_date->format('M j, Y') . ' to ' . $class->term->end_date->format('M j, Y') . ').';
        }

        $holiday = NonClassDay::whereDate('date', $date)->first();
        if ($holiday) {
            $problems[] = "This is a non-class day: {$holiday->description}.";
        }

        if (! $class->meetings->contains('day_of_week', $date->dayOfWeekIso)) {
            $problems[] = 'This class does not meet on ' . ClassMeeting::DAYS[$date->dayOfWeekIso] . 's.';
        }

        return $problems;
    }

    /**
     * What this user may do with the sheet.
     *  - registrar: read only.
     *  - teacher (owner): edit inside the edit window.
     *  - admin: edit any date; a reason is needed outside the window.
     *
     * @return array{mode: string, needsReason: bool, messages: string[]}
     */
    public static function access(User $user, ClassOffering $class, Carbon $date): array
    {
        if ($user->role === 'registrar') {
            return ['mode' => 'view', 'needsReason' => false, 'messages' => []];
        }

        $problems = self::dateProblems($class, $date);
        if ($problems !== []) {
            return ['mode' => 'view', 'needsReason' => false, 'messages' => $problems];
        }

        $window = self::windowDays();
        $outsideWindow = $date->lt(today()->subDays($window));

        if ($user->role === 'admin') {
            return ['mode' => 'edit', 'needsReason' => $outsideWindow, 'messages' => []];
        }

        if ((int) $class->teacher_id !== (int) $user->id) {
            return ['mode' => 'view', 'needsReason' => false, 'messages' => ['This is not your class.']];
        }

        if ($outsideWindow) {
            return ['mode' => 'view', 'needsReason' => false, 'messages' => [
                "Teachers can change attendance only within {$window} days. Ask an administrator to correct older records.",
            ]];
        }

        return ['mode' => 'edit', 'needsReason' => false, 'messages' => []];
    }

    /**
     * Students who belong on the sheet: officially enrolled on or before the date,
     * plus anyone who already has a record on it.
     *
     * @param  int[]  $alsoIds
     */
    public static function eligible(ClassOffering $class, Carbon $date, array $alsoIds = []): Collection
    {
        $ids = Enrollment::where('class_offering_id', $class->id)
            ->where('status', Enrollment::STATUS_ENROLLED)
            ->where(fn ($q) => $q->whereNull('enrolled_at')->orWhereDate('enrolled_at', '<=', $date))
            ->pluck('student_id')
            ->merge($alsoIds)
            ->unique()
            ->values();

        return Student::withTrashed()->with('program')->whereIn('id', $ids)
            ->orderBy('last_name')->orderBy('first_name')->get();
    }

    /**
     * Recent days this class met, newest first, with whether a sheet exists.
     *
     * @return array<int, array{date: Carbon, recorded: bool}>
     */
    public static function classDays(ClassOffering $class, int $limit = 10): array
    {
        $class->loadMissing(['term', 'meetings']);

        $weekdays = $class->meetings->pluck('day_of_week')->unique()->all();
        $start = $class->term->start_date->copy();
        $end = min(today(), $class->term->end_date->copy());

        $holidays = NonClassDay::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')->map(fn ($d) => $d->toDateString())->all();

        $recorded = AttendanceSession::where('class_offering_id', $class->id)
            ->pluck('session_date')->map(fn ($d) => $d->toDateString())->all();

        $days = [];
        for ($day = $end->copy(); $day->gte($start) && count($days) < $limit; $day->subDay()) {
            if (in_array($day->dayOfWeekIso, $weekdays, true) && ! in_array($day->toDateString(), $holidays, true)) {
                $days[] = ['date' => $day->copy(), 'recorded' => in_array($day->toDateString(), $recorded, true)];
            }
        }

        return $days;
    }

    /**
     * Save a whole sheet in one transaction.
     *
     * @param  array<int|string, array{status: string, remarks?: ?string}>  $rows  keyed by student id
     * @return array{new: bool, changes: int, counts: array}
     */
    public static function save(User $user, ClassOffering $class, Carbon $date, array $rows, ?string $reason): array
    {
        return DB::transaction(function () use ($user, $class, $date, $rows, $reason) {
            $session = AttendanceSession::where('class_offering_id', $class->id)
                ->whereDate('session_date', $date)->lockForUpdate()->first();
            $isNew = $session === null;

            $existing = $isNew ? collect() : $session->records()->get()->keyBy('student_id');
            $eligible = self::eligible($class, $date, $existing->keys()->all())->keyBy('id');

            $problems = [];
            foreach ($eligible as $id => $student) {
                if (! isset($rows[$id])) {
                    $problems[] = "Choose a status for {$student->full_name}.";
                }
            }
            foreach ($rows as $id => $row) {
                $student = $eligible->get((int) $id);

                if (! $student) {
                    $problems[] = "Student #{$id} is not on this class sheet.";
                } elseif (($row['status'] ?? '') === AttendanceRecord::EXCUSED && mb_strlen(trim((string) ($row['remarks'] ?? ''))) < 3) {
                    $problems[] = "Add a remark explaining the excused absence of {$student->full_name}.";
                }
            }
            if ($problems !== []) {
                throw new AttendanceException($problems);
            }

            if ($isNew) {
                try {
                    $session = AttendanceSession::create([
                        'class_offering_id' => $class->id,
                        'session_date' => $date->toDateString(),
                        'taken_by' => $user->id,
                    ]);
                } catch (QueryException) {
                    throw new AttendanceException(['Someone else just saved this sheet. Reload the page and try again.']);
                }
            }

            $old = [];
            $new = [];

            foreach ($rows as $id => $row) {
                $student = $eligible[(int) $id];
                $status = $row['status'];
                $remarks = trim((string) ($row['remarks'] ?? '')) ?: null;
                $record = $existing->get((int) $id);

                if (! $record) {
                    AttendanceRecord::create([
                        'attendance_session_id' => $session->id,
                        'student_id' => $student->id,
                        'status' => $status,
                        'remarks' => $remarks,
                        'recorded_by' => $user->id,
                    ]);

                    if (! $isNew) {
                        $old[$student->student_number] = 'none';
                        $new[$student->student_number] = $status;
                    }

                    continue;
                }

                if ($record->status !== $status || $record->remarks !== $remarks) {
                    $old[$student->student_number] = $record->status . ($record->remarks ? " ({$record->remarks})" : '');
                    $new[$student->student_number] = $status . ($remarks ? " ({$remarks})" : '');

                    $record->update(['status' => $status, 'remarks' => $remarks, 'updated_by' => $user->id]);
                }
            }

            $counts = AttendanceStats::counts(array_count_values(array_column($rows, 'status')));
            $summary = "{$counts['present']} present, {$counts['late']} late, {$counts['absent']} absent, {$counts['excused']} excused";

            if ($isNew) {
                AuditLogger::log('attendance.recorded', $session,
                    "Recorded attendance for {$class->class_code} on {$date->toDateString()} ({$summary})", null, ['counts' => $counts]);
            } elseif ($old !== []) {
                AuditLogger::log('attendance.corrected', $session,
                    "Corrected attendance for {$class->class_code} on {$date->toDateString()} (" . count($old) . ' change' . (count($old) === 1 ? '' : 's') . ')',
                    $old, $new + array_filter(['reason' => $reason]));
            }

            return ['new' => $isNew, 'changes' => $isNew ? 0 : count($old), 'counts' => $counts];
        });
    }
}
