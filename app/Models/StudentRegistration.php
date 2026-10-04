<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRegistration extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'pending' => 'Pending review',
        'under_review' => 'Under review',
        'verified' => 'Documents verified',
        'needs_correction' => 'Needs correction',
    ];

    public const DOCUMENT_STATUS_LABELS = [
        'pending' => 'Pending review',
        'verified' => 'Verified',
        'needs_correction' => 'Needs correction',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'emergency_same_address' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? str($this->status)->headline()->toString();
    }
}
