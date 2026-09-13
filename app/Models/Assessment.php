<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    protected $fillable = ['section_id', 'grading_category_id', 'created_by', 'title', 'type', 'instructions', 'rubric', 'max_score', 'weight', 'sort_order', 'due_at', 'published_at', 'status'];

    protected function casts(): array
    {
        return ['max_score' => 'decimal:2', 'weight' => 'decimal:2', 'due_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function rubricCriteria(): array
    {
        if (blank($this->rubric)) {
            return [];
        }

        $decoded = json_decode($this->rubric, true);
        if (is_array($decoded) && ($decoded['version'] ?? null) === 1 && is_array($decoded['criteria'] ?? null)) {
            return $decoded['criteria'];
        }

        return [[
            'title' => 'Existing rubric',
            'description' => $this->rubric,
            'percentage' => 100,
            'score' => (float) $this->max_score,
        ]];
    }

    public function formattedRubric(): string
    {
        return collect($this->rubricCriteria())->values()->map(function (array $criterion, int $index): string {
            return implode("\n", [
                'Criterion '.($index + 1).': '.($criterion['title'] ?? 'Untitled criterion'),
                'Description: '.($criterion['description'] ?? ''),
                'Percentage: '.number_format((float) ($criterion['percentage'] ?? 0), 2).'%',
                'Maximum score: '.number_format((float) ($criterion['score'] ?? 0), 2).' points',
            ]);
        })->implode("\n\n");
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
