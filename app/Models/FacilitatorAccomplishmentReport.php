<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitatorAccomplishmentReport extends Model
{
    public const STATUSES = [
        'submitted' => 'For review',
        'reviewed' => 'Reviewed',
        'needs_revision' => 'Needs revision',
    ];

    protected $fillable = [
        'facilitator_id', 'component_id', 'academic_year', 'semester', 'report_month',
        'accomplishment_summary', 'file_path', 'original_name', 'mime_type', 'file_size',
        'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'report_month' => 'date',
            'file_size' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function semesterLabel(): string
    {
        return NstpSection::SEMESTERS[$this->semester] ?? str($this->semester)->headline()->toString();
    }
}
