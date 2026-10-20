<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricTemplate extends Model
{
    protected $fillable = [
        'facilitator_id',
        'name',
        'assessment_type',
        'source_title',
        'criteria',
        'maximum_score',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'maximum_score' => 'decimal:2',
        ];
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }
}
