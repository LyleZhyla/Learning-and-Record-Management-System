<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityProjectActivity extends Model
{
    public const STATUSES = [
        'planned' => 'Planned',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'community_project_id', 'title', 'description', 'scheduled_date', 'status',
        'accomplishment_notes', 'completed_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CommunityProjectDocument::class, 'community_project_activity_id');
    }
}
