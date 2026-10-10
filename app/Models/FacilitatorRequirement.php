<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FacilitatorRequirement extends Model
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    protected $fillable = [
        'title', 'slug', 'description', 'instructions', 'component_id', 'accepted_extensions',
        'max_size_kb', 'is_required', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'accepted_extensions' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FacilitatorRequirementSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedTypesLabel(): string
    {
        return collect($this->accepted_extensions)->map(fn (string $extension): string => strtoupper($extension))->implode(', ');
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'facilitator-requirement';
        $slug = $base;
        $suffix = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
