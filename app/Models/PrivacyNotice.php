<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyNotice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
