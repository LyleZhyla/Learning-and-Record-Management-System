<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentIntervention extends Model
{
    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'escalated' => 'Escalated', 'resolved' => 'Resolved'];

    public const REFERRAL_TYPES = ['academic_support' => 'Academic support', 'attendance_counseling' => 'Attendance counseling', 'guidance_referral' => 'Guidance referral', 'financial_support' => 'Financial support', 'health_and_wellness' => 'Health and wellness', 'other' => 'Other'];

    protected $fillable = ['student_id', 'section_id', 'risk_snapshot_id', 'assigned_to', 'created_by', 'status', 'referral_type', 'severity', 'notes', 'action_plan', 'follow_up_date', 'resolution', 'escalation_count', 'last_reviewed_at', 'escalated_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['follow_up_date' => 'date', 'escalation_count' => 'integer', 'last_reviewed_at' => 'datetime', 'escalated_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function riskSnapshot(): BelongsTo
    {
        return $this->belongsTo(StudentRiskSnapshot::class, 'risk_snapshot_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InterventionActivity::class, 'intervention_id')->latest();
    }
}
