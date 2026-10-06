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
        'verified' => 'Approved / account created',
        'needs_correction' => 'Needs correction',
    ];

    public const DOCUMENT_STATUS_LABELS = [
        'pending' => 'Pending review',
        'verified' => 'Verified',
        'needs_correction' => 'Needs correction',
    ];

    public const NSTP_LEVELS = [
        'nstp_1' => 'NSTP 1 — first NSTP semester',
        'nstp_2' => 'NSTP 2 — completed NSTP 1 previously',
    ];

    public static function nstpLevelForSemester(string $semester): string
    {
        return $semester === 'second' ? 'nstp_2' : 'nstp_1';
    }

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
