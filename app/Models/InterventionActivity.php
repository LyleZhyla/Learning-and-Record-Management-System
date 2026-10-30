<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionActivity extends Model
{
    protected $fillable = ['intervention_id', 'actor_id', 'action', 'details', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(StudentIntervention::class, 'intervention_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
