<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    protected $fillable = ['section_id', 'grading_category_id', 'created_by', 'title', 'type', 'instructions', 'max_score', 'weight', 'sort_order', 'due_at', 'published_at', 'status'];

    protected function casts(): array
    {
        return ['max_score' => 'decimal:2', 'weight' => 'decimal:2', 'due_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function gradingCategory(): BelongsTo
    {
        return $this->belongsTo(GradingCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssessmentSubmission::class);
    }

    public function omrSheet(): HasOne
    {
        return $this->hasOne(OmrSheet::class);
    }
}
