<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NstpSection extends Model
{
    use HasFactory;

    public const SEMESTERS = [
        'first' => 'First Semester',
        'second' => 'Second Semester',
        'summer' => 'Summer Term',
    ];

    protected $fillable = [
        'component_id',
        'facilitator_id',
        'code',
        'name',
        'academic_year',
        'semester',
        'capacity',
        'status',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(NstpEnrollment::class, 'section_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'section_id');
    }

    public function learningMaterials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class, 'section_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'section_id');
    }

    public function semesterLabel(): string
    {
        return self::SEMESTERS[$this->semester] ?? str($this->semester)->headline()->toString();
    }
}
