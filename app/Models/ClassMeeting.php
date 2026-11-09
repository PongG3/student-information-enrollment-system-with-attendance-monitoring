<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMeeting extends Model
{
    public const DAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    protected $fillable = ['class_offering_id', 'day_of_week', 'start_time', 'end_time', 'room'];

    public function classOffering(): BelongsTo
    {
        return $this->belongsTo(ClassOffering::class);
    }
}
