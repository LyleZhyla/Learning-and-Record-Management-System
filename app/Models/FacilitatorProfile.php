<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitatorProfile extends Model
{
    public const EMPLOYMENT_STATUS_LABELS = [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
        'contractual' => 'Contractual',
        'visiting' => 'Visiting / Adjunct',
    ];

    protected $fillable = [
        'user_id',
        'employee_number',
        'department',
        'designation',
        'employment_status',
        'contact_number',
        'specialization',
        'professional_summary',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employmentStatusLabel(): string
    {
        return self::EMPLOYMENT_STATUS_LABELS[$this->employment_status]
            ?? (filled($this->employment_status) ? str($this->employment_status)->headline()->toString() : 'Not provided');
    }
}
