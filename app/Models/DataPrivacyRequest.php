<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataPrivacyRequest extends Model
{
    public const TYPES = [
        'export' => 'Data export/access',
        'correction' => 'Data correction',
        'deletion' => 'Data deletion review',
        'consent_withdrawal' => 'Consent withdrawal',
    ];

    public const STATUSES = ['submitted', 'identity_verification', 'in_progress', 'completed', 'rejected'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
