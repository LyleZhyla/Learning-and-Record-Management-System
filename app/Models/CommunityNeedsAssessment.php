<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityNeedsAssessment extends Model
{
    public const STATUSES = ['draft', 'verified', 'closed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['assessed_on' => 'date', 'verified_at' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CommunityPartner::class, 'community_partner_id');
    }

    public function priorities(): HasMany
    {
        return $this->hasMany(CommunityNeedPriority::class)->orderByRaw("CASE priority_level WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END");
    }
}
