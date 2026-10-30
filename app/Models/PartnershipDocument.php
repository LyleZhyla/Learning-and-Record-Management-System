<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnershipDocument extends Model
{
    public const TYPES = [
        'moa' => 'Memorandum of Agreement',
        'mou' => 'Memorandum of Understanding',
        'endorsement' => 'Endorsement / Resolution',
        'data_sharing' => 'Data-sharing agreement',
        'other' => 'Other partnership document',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'expiration_date' => 'date'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CommunityPartner::class, 'community_partner_id');
    }
}
