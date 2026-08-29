<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrSheet extends Model
{
    protected $fillable = ['assessment_id', 'created_by', 'item_count', 'choice_count', 'answer_key'];

    protected function casts(): array
    {
        return ['answer_key' => 'array', 'item_count' => 'integer', 'choice_count' => 'integer'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(OmrScanResult::class);
    }
}
