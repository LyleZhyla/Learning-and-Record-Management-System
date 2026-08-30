<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use Archivable;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'actor_name', 'actor_email', 'actor_role', 'action', 'description',
        'method', 'route_name', 'path', 'ip_address', 'user_agent', 'status_code',
        'duration_ms', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
