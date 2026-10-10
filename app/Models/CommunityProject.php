<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityProject extends Model
{
    public const APPROVAL_STATUSES = [
        'pending' => 'Pending review',
        'needs_revision' => 'Needs revision',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public const IMPLEMENTATION_STATUSES = [
        'proposed' => 'Proposed',
        'planning' => 'Planning',
        'ongoing' => 'Ongoing',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'reference_number', 'title', 'component_id', 'section_id', 'proposed_by',
        'description', 'objectives', 'beneficiaries', 'beneficiary_count', 'location',
        'budget', 'start_date', 'end_date', 'approval_status', 'approval_notes',
        'approved_by', 'approved_at', 'implementation_status', 'implementation_notes',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CommunityProjectActivity::class)->orderBy('scheduled_date')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('due_at')->orderBy('id');
    }

    public function approvalLabel(): string
    {
        return self::APPROVAL_STATUSES[$this->approval_status] ?? str($this->approval_status)->headline()->toString();
    }

    public function implementationLabel(): string
    {
        return self::IMPLEMENTATION_STATUSES[$this->implementation_status] ?? str($this->implementation_status)->headline()->toString();
    }
}
