<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NstpStudentSerialNumber extends Model
{
    protected $fillable = ['release_id', 'enrollment_id', 'student_id', 'serial_number', 'encoded_by'];

    public function release(): BelongsTo
    {
        return $this->belongsTo(NstpSerialNumberRelease::class, 'release_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(NstpEnrollment::class, 'enrollment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}
