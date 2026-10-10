<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NstpServiceRequest extends Model
{
    public const REQUEST_TYPES = [
        'serial_number' => 'Serial Number',
        'certificate_of_completion' => 'Certificate of Completion',
        'certification' => 'Certification',
        'assistance' => 'Event Assistance',
    ];

    public const ASSISTANCE_TYPES = [
        'honor_guard' => 'Honor Guard',
        'colors' => 'Colors',
        'marshal' => 'Marshal',
        'collaboration' => 'Collaboration',
    ];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'needs_information' => 'Needs information',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'declined' => 'Declined',
    ];

    protected $fillable = [
        'reference_code', 'request_type', 'assistance_type', 'requester_name', 'email',
        'contact_number', 'student_number', 'program', 'graduation_year', 'purpose',
        'event_name', 'event_date', 'event_location', 'expected_participants',
        'attachment_path', 'attachment_original_name', 'attachment_mime_type',
        'attachment_size_bytes', 'status', 'status_note', 'processed_by',
        'processed_at', 'privacy_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'processed_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NstpServiceRequest $request): void {
            if (blank($request->reference_code)) {
                do {
                    $reference = 'NSTP-'.now()->format('ym').'-'.Str::upper(Str::random(8));
                } while (static::where('reference_code', $reference)->exists());

                $request->reference_code = $reference;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference_code';
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function requestTypeLabel(): string
    {
        return self::REQUEST_TYPES[$this->request_type] ?? str($this->request_type)->headline()->toString();
    }

    public function assistanceTypeLabel(): ?string
    {
        return $this->assistance_type
            ? (self::ASSISTANCE_TYPES[$this->assistance_type] ?? str($this->assistance_type)->headline()->toString())
            : null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}
