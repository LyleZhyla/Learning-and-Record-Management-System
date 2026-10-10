<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitatorRequirementSubmission extends Model
{
    public const STATUSES = [
        'pending' => 'Pending review',
        'verified' => 'Verified',
        'needs_correction' => 'Needs correction',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'facilitator_requirement_id', 'facilitator_id', 'file_path', 'original_name',
        'mime_type', 'size_bytes', 'facilitator_notes', 'status', 'review_notes',
        'reviewed_by', 'reviewed_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(FacilitatorRequirement::class, 'facilitator_requirement_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
