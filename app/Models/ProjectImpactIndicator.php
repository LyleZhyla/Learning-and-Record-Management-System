<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectImpactIndicator extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'baseline_value' => 'decimal:2', 'target_value' => 'decimal:2', 'post_project_value' => 'decimal:2',
            'baseline_measured_on' => 'date', 'post_measured_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    public function change(): ?float
    {
        return $this->post_project_value === null ? null : (float) $this->post_project_value - (float) $this->baseline_value;
    }
}
