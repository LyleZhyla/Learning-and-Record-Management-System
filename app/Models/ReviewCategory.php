<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReviewCategory extends Model
{
    public const SCOPES = [
        'registration' => 'Registration status',
        'registration_document' => 'Registration document decision',
        'document_submission' => 'Configurable document decision',
    ];

    public const OUTCOMES = [
        'pending' => 'Pending / not started',
        'in_progress' => 'In progress',
        'approved' => 'Approved / verified',
        'correction' => 'Needs correction',
    ];

    public const FALLBACKS = [
        'registration' => [
            'pending' => ['Pending review', 'pending', '#d18a16'],
            'under_review' => ['Under review', 'in_progress', '#2f73c8'],
            'verified' => ['Approved / account created', 'approved', '#168865'],
            'needs_correction' => ['Needs correction', 'correction', '#c44f61'],
        ],
        'registration_document' => [
            'pending' => ['Pending review', 'pending', '#d18a16'],
            'verified' => ['Verified', 'approved', '#168865'],
            'needs_correction' => ['Needs correction', 'correction', '#c44f61'],
        ],
        'document_submission' => [
            'pending' => ['Pending review', 'pending', '#d18a16'],
            'verified' => ['Verified', 'approved', '#168865'],
            'needs_correction' => ['Needs correction', 'correction', '#c44f61'],
        ],
    ];

    protected $fillable = [
        'scope', 'name', 'slug', 'outcome', 'color', 'is_active', 'is_default',
        'is_system', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean', 'is_system' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    public static function categories(string $scope, bool $activeOnly = false)
    {
        if (! Schema::hasTable('review_categories')) {
            return collect();
        }

        return static::forScope($scope)
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array<string, string> */
    public static function labels(string $scope, bool $activeOnly = false): array
    {
        $categories = static::categories($scope, $activeOnly);
        if ($categories->isNotEmpty()) {
            return $categories->pluck('name', 'slug')->all();
        }

        return collect(self::FALLBACKS[$scope] ?? [])->mapWithKeys(
            fn (array $definition, string $slug): array => [$slug => $definition[0]],
        )->all();
    }

    public static function findStatus(string $scope, ?string $slug): ?self
    {
        if (! $slug || ! Schema::hasTable('review_categories')) {
            return null;
        }

        return static::forScope($scope)->where('slug', $slug)->first();
    }

    public static function labelFor(string $scope, ?string $slug): string
    {
        return static::findStatus($scope, $slug)?->name
            ?? (self::FALLBACKS[$scope][$slug][0] ?? str((string) $slug)->headline()->toString());
    }

    public static function colorFor(string $scope, ?string $slug): string
    {
        return static::findStatus($scope, $slug)?->color
            ?? (self::FALLBACKS[$scope][$slug][2] ?? '#687589');
    }

    public static function outcomeFor(string $scope, ?string $slug): string
    {
        return static::findStatus($scope, $slug)?->outcome
            ?? (self::FALLBACKS[$scope][$slug][1] ?? 'pending');
    }

    public static function defaultSlug(string $scope, string $outcome, string $fallback): string
    {
        if (Schema::hasTable('review_categories')) {
            $slug = static::forScope($scope)->where('outcome', $outcome)->where('is_active', true)
                ->orderByDesc('is_default')->orderBy('sort_order')->value('slug');
            if ($slug) {
                return $slug;
            }
        }

        return $fallback;
    }

    public static function uniqueSlug(string $scope, string $name): string
    {
        $base = Str::substr(Str::slug($name), 0, 16) ?: 'review-status';
        $slug = $base;
        $suffix = 2;
        while (static::forScope($scope)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
