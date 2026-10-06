<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLearningRecommendation extends Model
{
    protected $fillable = [
        'student_id',
        'nstp_enrollment_id',
        'preferences',
        'guidance',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'guidance' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(NstpEnrollment::class, 'nstp_enrollment_id');
    }
}
