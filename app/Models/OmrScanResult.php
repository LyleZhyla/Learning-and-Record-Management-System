<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrScanResult extends Model
{
    protected $fillable = ['omr_sheet_id', 'student_id', 'scanned_by', 'answers', 'correct_count', 'blank_count', 'score', 'confidence'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'score' => 'decimal:2', 'confidence' => 'decimal:2'];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(OmrSheet::class, 'omr_sheet_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
