<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'short_name', 'slug', 'code', 'email', 'phone', 'website',
        'address', 'logo_path', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class)->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
