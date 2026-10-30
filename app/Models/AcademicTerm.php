<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    public const STATUSES = ['open' => 'Open', 'active' => 'Active', 'grading' => 'Grading', 'finalized' => 'Finalized', 'archived' => 'Archived'];

    protected $fillable = ['academic_year', 'semester', 'status', 'starts_on', 'ends_on', 'grade_submission_deadline', 'is_current', 'finalized_by', 'finalized_at', 'archived_by', 'archived_at', 'snapshot_version'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'grade_submission_deadline' => 'datetime', 'is_current' => 'boolean', 'finalized_at' => 'datetime', 'archived_at' => 'datetime', 'snapshot_version' => 'integer'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TermGradeApproval::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AcademicTermTransition::class)->latest();
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AcademicTermSnapshot::class)->latest('version');
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(CompletionClearance::class);
    }
}
