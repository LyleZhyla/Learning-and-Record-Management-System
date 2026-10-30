<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompletionClearance extends Model
{
    protected $fillable = ['academic_term_id', 'enrollment_id', 'student_id', 'section_id', 'status', 'final_percentage', 'remarks', 'cleared_by', 'cleared_at'];

    protected function casts(): array
    {
        return ['final_percentage' => 'float', 'cleared_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(NstpEnrollment::class);
    }
}
