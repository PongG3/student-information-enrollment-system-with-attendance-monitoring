<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A subject the student already completed at another school, accepted by the registrar (transferees). */
class CreditedSubject extends Model
{
    protected $fillable = ['student_id', 'subject_id', 'school', 'note', 'credited_by'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credited_by');
    }
}
