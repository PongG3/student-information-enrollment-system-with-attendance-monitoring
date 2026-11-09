<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardian extends Model
{
    protected $fillable = [
        'student_id', 'full_name', 'relationship', 'contact_number', 'address', 'is_emergency_contact',
    ];

    protected function casts(): array
    {
        return ['is_emergency_contact' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
