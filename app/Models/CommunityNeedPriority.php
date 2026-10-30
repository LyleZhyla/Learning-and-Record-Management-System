<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityNeedPriority extends Model
{
    public const CATEGORIES = [
        'education' => 'Education and literacy',
        'health' => 'Health and well-being',
        'environment' => 'Environment and climate',
        'livelihood' => 'Livelihood and economic resilience',
        'disaster_preparedness' => 'Disaster preparedness',
        'peace_safety' => 'Peace, safety, and inclusion',
        'digital_access' => 'Digital access and skills',
        'governance' => 'Community governance',
        'other' => 'Other priority',
    ];

    public const PRIORITY_LEVELS = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'matched', 'addressed', 'deferred'];

    public const SDGS = [
        'SDG1' => 'No Poverty', 'SDG2' => 'Zero Hunger', 'SDG3' => 'Good Health and Well-being',
        'SDG4' => 'Quality Education', 'SDG5' => 'Gender Equality', 'SDG6' => 'Clean Water and Sanitation',
        'SDG7' => 'Affordable and Clean Energy', 'SDG8' => 'Decent Work and Economic Growth',
        'SDG9' => 'Industry, Innovation and Infrastructure', 'SDG10' => 'Reduced Inequalities',
        'SDG11' => 'Sustainable Cities and Communities', 'SDG12' => 'Responsible Consumption and Production',
        'SDG13' => 'Climate Action', 'SDG14' => 'Life Below Water', 'SDG15' => 'Life on Land',
        'SDG16' => 'Peace, Justice and Strong Institutions', 'SDG17' => 'Partnerships for the Goals',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sdg_codes' => 'array', 'required_skills' => 'array', 'estimated_budget' => 'decimal:2'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(CommunityNeedsAssessment::class, 'community_needs_assessment_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CommunityProject::class);
    }
}
