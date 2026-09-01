<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = ['section_id', 'created_by', 'title', 'starts_at', 'late_after', 'ends_at', 'token', 'qr_payload', 'qr_svg', 'status', 'scan_mode'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'late_after' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isOpenForCheckIn(): bool
    {
        return $this->status === 'open' && now()->between($this->starts_at, $this->ends_at);
    }
}
