<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in the breach register (see the migration and the Data Breaches page). */
class DataBreach extends Model
{
    protected $fillable = [
        'discovered_at', 'summary', 'data_affected', 'people_affected', 'actions_taken',
        'npc_notified_on', 'subjects_notified_on', 'status', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['discovered_at' => 'datetime', 'npc_notified_on' => 'date', 'subjects_notified_on' => 'date'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The 72-hour deadline to tell the National Privacy Commission about a serious breach. */
    public function npcDeadline(): \Carbon\Carbon
    {
        return $this->discovered_at->copy()->addHours(72);
    }
}
