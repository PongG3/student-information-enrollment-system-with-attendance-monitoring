<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['code', 'title', 'units', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'units' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function classOfferings(): HasMany
    {
        return $this->hasMany(ClassOffering::class);
    }
}
