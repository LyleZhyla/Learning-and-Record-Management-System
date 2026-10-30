<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionPolicy extends Model
{
    public const RECORD_TYPES = [
        'audit_logs' => 'System audit logs',
        'student_notifications' => 'Student notifications',
        'ai_chat_messages' => 'AI chat messages',
        'sensitive_field_access_logs' => 'Sensitive-field access logs',
        'privacy_consents' => 'Privacy consent records',
    ];

    public const DISPOSITIONS = ['archive', 'delete', 'anonymize'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_enforced_at' => 'datetime'];
    }
}
