<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'blocked' => 'Blocked',
        'submitted' => 'Submitted for review',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'community_project_id', 'title', 'description', 'assigned_to', 'assigned_by',
        'priority', 'status', 'due_at', 'progress_percentage', 'submission_notes',
        'evidence_path', 'evidence_original_name', 'submitted_at', 'review_notes',
        'reviewed_by', 'reviewed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? str($this->priority)->headline()->toString();
    }

    public function isOverdue(): bool
    {
        return $this->due_at?->isPast() && ! in_array($this->status, ['completed', 'cancelled'], true);
    }
}
