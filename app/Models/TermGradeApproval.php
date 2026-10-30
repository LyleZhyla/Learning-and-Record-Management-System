<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermGradeApproval extends Model
{
    protected $fillable = ['academic_term_id', 'section_id', 'approved_by', 'remarks', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
