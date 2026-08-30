<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Archivable
{
    public static function bootArchivable(): void
    {
        static::addGlobalScope('not_archived', fn (Builder $query) =>
            $query->whereNull($query->qualifyColumn('archived_at'))
        );
    }

    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('not_archived');
    }

    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('not_archived')->whereNotNull($query->qualifyColumn('archived_at'));
    }
}
