<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRestorationDrill extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
