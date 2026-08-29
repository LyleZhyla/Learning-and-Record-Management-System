<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingCategory extends Model
{
    protected $fillable = ['section_id', 'name', 'weight', 'color', 'sort_order'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('sort_order')->orderBy('id');
    }
}
