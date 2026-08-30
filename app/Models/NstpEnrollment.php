<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NstpEnrollment extends Model
{
    use HasFactory;

    public const SHIRT_SIZES = [
        'XS' => 'Extra Small (XS)',
        'S' => 'Small (S)',
        'M' => 'Medium (M)',
        'L' => 'Large (L)',
        'XL' => 'Extra Large (XL)',
        '2XL' => '2X Large (2XL)',
        '3XL' => '3X Large (3XL)',
    ];

    public const ROTC_CATEGORIES = [
        'MS-1' => 'MS-1',
        'MS-31' => 'MS-31',
        'MS-41' => 'MS-41',
    ];

    protected $fillable = [
        'student_id',
        'component_id',
        'section_id',
        'academic_year',
        'semester',
        'shirt_size',
        'rotc_category',
        'rotc_proof_path',
        'rotc_proof_original_name',
        'rotc_approval_status',
        'rotc_approved_by',
        'rotc_approved_at',
        'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function rotcApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotc_approved_by');
    }

    protected function casts(): array
    {
        return ['rotc_approved_at' => 'datetime'];
    }
}
