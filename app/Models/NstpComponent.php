<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NstpComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_section_capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(NstpSection::class, 'component_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(NstpEnrollment::class, 'component_id');
    }
}
