<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionSchedule extends Model
{
    protected $fillable = ['section_id', 'day_of_week', 'starts_at', 'ends_at', 'is_automatic', 'updated_by'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'is_automatic' => 'boolean'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class);
    }
}
