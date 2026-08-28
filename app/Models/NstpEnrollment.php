<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NstpEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'component_id',
        'section_id',
        'academic_year',
        'semester',
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
}
