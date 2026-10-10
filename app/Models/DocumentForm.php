<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentForm extends Model
{
    public const CATEGORIES = ['document' => 'Document requirement', 'form' => 'Downloadable form'];

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    protected $fillable = [
        'title', 'slug', 'category', 'description', 'instructions', 'component_id',
        'accepted_extensions', 'max_size_kb', 'requires_submission', 'is_required',
        'template_path', 'template_original_name', 'opens_at', 'closes_at',
        'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'accepted_extensions' => 'array',
            'requires_submission' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DocumentSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $dates) => $dates->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn (Builder $dates) => $dates->whereNull('closes_at')->orWhere('closes_at', '>=', now()));
    }

    public function appliesTo(?NstpEnrollment $enrollment): bool
    {
        return $this->component_id === null || (int) $this->component_id === (int) $enrollment?->component_id;
    }

    public function acceptedTypesLabel(): string
    {
        return collect($this->accepted_extensions ?? [])->map(fn (string $extension): string => strtoupper($extension))->implode(', ');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? str($this->category)->headline()->toString();
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'document-form';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
