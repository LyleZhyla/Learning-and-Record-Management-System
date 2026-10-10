<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilitatorHonorarium extends Model
{
    public const STATUSES = [
        'for_document_preparation' => 'For document preparation',
        'documents_prepared' => 'Documents prepared',
        'forwarded_to_cashier' => 'Forwarded to University Cashier',
        'returned_for_correction' => 'Returned for correction',
    ];

    protected $fillable = [
        'reference_number', 'facilitator_id', 'component_id', 'academic_year', 'semester',
        'period_start', 'period_end', 'gross_amount', 'deductions', 'net_amount', 'status',
        'request_notes', 'requested_by', 'requested_at', 'disbursement_voucher_number',
        'obligation_request_number', 'payroll_reference', 'preparation_notes', 'prepared_by',
        'prepared_at', 'cashier_forwarded_at', 'voucher_generated_by', 'voucher_generated_at',
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
            'prepared_at' => 'datetime',
            'cashier_forwarded_at' => 'datetime',
            'voucher_generated_at' => 'datetime',
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

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
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
