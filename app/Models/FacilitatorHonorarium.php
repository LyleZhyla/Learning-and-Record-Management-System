<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitatorHonorarium extends Model
{
    public const STATUSES = [
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved for payment',
        'rejected' => 'Returned / rejected',
        'disbursed' => 'Disbursed',
    ];

    protected $fillable = [
        'reference_number', 'facilitator_id', 'component_id', 'academic_year', 'semester',
        'period_start', 'period_end', 'gross_amount', 'deductions', 'net_amount', 'status',
        'request_notes', 'requested_by', 'requested_at', 'approval_notes', 'approved_by',
        'approved_at', 'disbursement_reference', 'disbursed_by', 'disbursed_at',
        'payslip_generated_by', 'payslip_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_amount' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'payslip_generated_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
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
