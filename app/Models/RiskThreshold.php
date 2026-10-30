<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskThreshold extends Model
{
    protected $fillable = ['component_id', 'minimum_attendance_percentage', 'minimum_grade_percentage', 'maximum_missing_requirements', 'follow_up_days', 'escalation_days', 'minimum_improvement_points', 'updated_by'];

    protected function casts(): array
    {
        return ['minimum_attendance_percentage' => 'float', 'minimum_grade_percentage' => 'float', 'maximum_missing_requirements' => 'integer', 'follow_up_days' => 'integer', 'escalation_days' => 'integer', 'minimum_improvement_points' => 'integer'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
