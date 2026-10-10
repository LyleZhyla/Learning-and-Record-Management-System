<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentAssessmentSetting extends Model
{
    protected $fillable = [
        'component_id',
        'allowed_types',
        'default_type',
        'default_max_score',
        'rubric_required_types',
        'passing_percentage',
        'highest_grade',
        'passing_grade',
        'failing_grade',
        'category_templates',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allowed_types' => 'array',
            'default_max_score' => 'decimal:2',
            'rubric_required_types' => 'array',
            'passing_percentage' => 'decimal:2',
            'highest_grade' => 'decimal:2',
            'passing_grade' => 'decimal:2',
            'failing_grade' => 'decimal:2',
            'category_templates' => 'array',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function configuredFor(NstpComponent $component): self
    {
        $stored = $component->relationLoaded('assessmentSetting')
            ? $component->assessmentSetting
            : $component->assessmentSetting()->first();

        if ($stored) {
            return $stored;
        }

        $definition = config('component_assessment_profiles.profiles.'.$component->code)
            ?? config('component_assessment_profiles.default');

        return new self([
            'component_id' => $component->id,
            'allowed_types' => $definition['allowed_types'],
            'default_type' => $definition['default_type'],
            'default_max_score' => $definition['default_max_score'],
            'rubric_required_types' => $definition['rubric_required_types'],
            'passing_percentage' => $definition['passing_percentage'],
            'highest_grade' => $definition['highest_grade'],
            'passing_grade' => $definition['passing_grade'],
            'failing_grade' => $definition['failing_grade'],
            'category_templates' => $definition['categories'],
        ]);
    }

    /** @return array<string, float> */
    public function gradingDefaults(): array
    {
        return [
            'passing_percentage' => (float) $this->passing_percentage,
            'highest_grade' => (float) $this->highest_grade,
            'passing_grade' => (float) $this->passing_grade,
            'failing_grade' => (float) $this->failing_grade,
        ];
    }

    public function allows(string $type): bool
    {
        return in_array($type, $this->allowed_types ?? [], true);
    }

    public function requiresRubric(string $type): bool
    {
        return in_array($type, $this->rubric_required_types ?? [], true);
    }
}
