<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $fillable = [
        'academic_year_id', 'name', 'start_date', 'end_date',
        'is_current', 'enrollment_open', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'enrollment_open' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classOfferings(): HasMany
    {
        return $this->hasMany(ClassOffering::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }
}
