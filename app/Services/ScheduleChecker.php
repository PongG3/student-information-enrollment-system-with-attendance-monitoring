<?php

namespace App\Services;

use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use Carbon\Carbon;

class ScheduleChecker
{
    /** Do two meetings overlap? Each is ['day' => 1-7, 'start' => 'HH:MM', 'end' => 'HH:MM']. */
    public static function overlap(array $a, array $b): bool
    {
        return (int) $a['day'] === (int) $b['day']
            && $a['start'] < $b['end']
            && $b['start'] < $a['end'];
    }

    public static function time(string $value): string
    {
        $value = strlen($value) === 5 ? $value . ':00' : $value;

        return Carbon::createFromFormat('H:i:s', $value)->format('g:i A');
    }

    /**
     * Problems with a class schedule: overlapping meetings inside the class,
     * the teacher teaching two classes at once, or a room used twice.
     *
     * @param  array<int, array{day:int,start:string,end:string,room:?string}>  $meetings
     * @return string[]
     */
    public static function classConflicts(int $termId, int $teacherId, array $meetings, ?int $ignoreClassId = null): array
    {
        $messages = [];

        foreach ($meetings as $i => $a) {
            foreach ($meetings as $j => $b) {
                if ($j > $i && self::overlap($a, $b)) {
                    $messages[] = 'Two meeting times of this class overlap on ' . ClassMeeting::DAYS[(int) $a['day']] . '.';
                }
            }
        }

        $others = ClassMeeting::with('classOffering')
            ->whereHas('classOffering', function ($query) use ($termId, $ignoreClassId) {
                $query->where('term_id', $termId)
                    ->where('status', '!=', ClassOffering::STATUS_CANCELLED)
                    ->when($ignoreClassId, fn ($q) => $q->where('id', '!=', $ignoreClassId));
            })
            ->get();

        foreach ($others as $other) {
            $otherMeeting = [
                'day' => $other->day_of_week,
                'start' => substr($other->start_time, 0, 5),
                'end' => substr($other->end_time, 0, 5),
            ];

            foreach ($meetings as $meeting) {
                if (! self::overlap($meeting, $otherMeeting)) {
                    continue;
                }

                $when = ClassMeeting::DAYS[$other->day_of_week] . ' ' . self::time($other->start_time) . ' to ' . self::time($other->end_time);

                if ((int) $other->classOffering->teacher_id === $teacherId) {
                    $messages[] = "This teacher already teaches {$other->classOffering->class_code} on {$when}.";
                }

                $room = (string) ($meeting['room'] ?? '');
                if ($room !== '' && strcasecmp($room, (string) $other->room) === 0) {
                    $messages[] = "Room {$room} is already used by {$other->classOffering->class_code} on {$when}.";
                }
            }
        }

        return array_values(array_unique($messages));
    }
}
