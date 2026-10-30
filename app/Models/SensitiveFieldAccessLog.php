<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensitiveFieldAccessLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['field_categories' => 'array', 'accessed_at' => 'datetime'];
    }
}
