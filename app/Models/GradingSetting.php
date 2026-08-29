<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingSetting extends Model
{
    protected $fillable = ['section_id', 'passing_percentage', 'highest_grade', 'passing_grade', 'failing_grade'];

    protected function casts(): array
    {
        return [
            'passing_percentage' => 'decimal:2',
            'highest_grade' => 'decimal:2',
            'passing_grade' => 'decimal:2',
            'failing_grade' => 'decimal:2',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }
}
