<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasFactory;

    public const AUDIENCES = [
        'all' => 'All portal users',
        'students' => 'Students',
        'facilitators' => 'Facilitators',
        'coordinators' => 'Coordinators',
    ];

    protected $fillable = [
        'author_id', 'component_id', 'title', 'body', 'audience', 'status', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')->withPivot('read_at');
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? str($this->audience)->headline()->toString();
    }
}
