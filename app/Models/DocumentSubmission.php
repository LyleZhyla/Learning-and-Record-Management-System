<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSubmission extends Model
{
    protected $fillable = [
        'document_form_id', 'user_id', 'enrollment_id', 'academic_year', 'semester',
        'file_path', 'original_filename', 'status', 'review_notes', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function documentForm(): BelongsTo
    {
        return $this->belongsTo(DocumentForm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(NstpEnrollment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return ReviewCategory::labelFor('document_submission', $this->status);
    }

    public function statusColor(): string
    {
        return ReviewCategory::colorFor('document_submission', $this->status);
    }

    public function isApproved(): bool
    {
        return ReviewCategory::outcomeFor('document_submission', $this->status) === 'approved';
    }
}
