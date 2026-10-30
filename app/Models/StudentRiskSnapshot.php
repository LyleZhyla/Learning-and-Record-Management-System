<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRiskSnapshot extends Model
{
    protected $fillable = ['student_id', 'section_id', 'component_id', 'risk_score', 'status', 'attendance_percentage', 'grade_percentage', 'missing_requirements', 'reasons', 'calculated_at'];

    protected function casts(): array
    {
        return ['risk_score' => 'integer', 'attendance_percentage' => 'float', 'grade_percentage' => 'float', 'missing_requirements' => 'integer', 'reasons' => 'array', 'calculated_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }
}
