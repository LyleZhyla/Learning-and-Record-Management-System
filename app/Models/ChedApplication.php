<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChedApplication extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'workbook_prepared' => 'Workbook prepared',
        'emailed' => 'Emailed to CHED',
        'acknowledged' => 'Acknowledged by CHED',
        'under_review' => 'Under CHED review',
        'returned' => 'Returned for correction',
        'approved' => 'Approved by CHED',
        'serials_released' => 'Serial numbers released',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'reference_number', 'academic_year', 'semester', 'student_count', 'status',
        'submission_email', 'ched_reference', 'notes', 'prepared_at', 'submitted_at',
        'acknowledged_at', 'serials_released_at', 'last_workbook_downloaded_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'serials_released_at' => 'datetime',
            'last_workbook_downloaded_at' => 'datetime',
        ];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ChedApplicationStatusHistory::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    /** @return array<string, string> */
    public function availableNextStatuses(): array
    {
        $allowed = match ($this->status) {
            'draft' => ['workbook_prepared'],
            'workbook_prepared' => ['emailed'],
            'emailed' => ['acknowledged', 'under_review', 'returned'],
            'acknowledged' => ['under_review', 'returned', 'approved'],
            'under_review' => ['returned', 'approved'],
            'returned' => ['workbook_prepared', 'emailed'],
            'approved' => ['serials_released'],
            'serials_released' => ['closed'],
            default => [],
        };

        return collect(self::STATUSES)->only($allowed)->all();
    }
}
