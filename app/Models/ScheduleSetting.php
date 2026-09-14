<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSetting extends Model
{
    protected $fillable = ['component_id', 'academic_year', 'semester', 'day_of_week', 'day_start', 'day_end', 'break_start', 'break_end', 'session_minutes', 'updated_by'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'session_minutes' => 'integer'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class);
    }
}
