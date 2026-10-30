<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyIncident extends Model
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['reported', 'investigating', 'contained', 'resolved', 'closed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['discovered_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
