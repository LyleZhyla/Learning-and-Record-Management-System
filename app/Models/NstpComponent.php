<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class NstpComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'logo_path',
        'default_section_capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function logoUrl(): string
    {
        $fallback = in_array(strtoupper($this->code), ['CWTS', 'LTS', 'ROTC'], true)
            ? 'images/branding/'.strtolower($this->code).'-logo.png'
            : null;

        if (filled($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return $fallback ? asset($fallback) : '';
    }

    public function sections(): HasMany
    {
        return $this->hasMany(NstpSection::class, 'component_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(NstpEnrollment::class, 'component_id');
    }

    public function assessmentSetting(): HasOne
    {
        return $this->hasOne(ComponentAssessmentSetting::class, 'component_id');
    }
}
