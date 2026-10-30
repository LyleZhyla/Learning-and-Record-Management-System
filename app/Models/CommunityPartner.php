<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPartner extends Model
{
    public const TYPES = [
        'barangay' => 'Barangay / LGU',
        'government_agency' => 'Government agency',
        'school' => 'School / Learning center',
        'ngo' => 'NGO / Civil society',
        'health_facility' => 'Health facility',
        'community_group' => 'Community organization',
        'other' => 'Other partner',
    ];

    public const VERIFICATION_STATUSES = ['pending', 'verified', 'suspended'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'service_areas' => 'array',
            'available_resources' => 'array',
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnershipDocument::class)->latest();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CommunityNeedsAssessment::class)->latest('assessed_on');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CommunityProject::class);
    }
}
