<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class InstitutionScope implements Scope
{
    public function __construct(private InstitutionContext $context) {}

    public function apply(Builder $builder, Model $model): void
    {
        $institutionId = $this->context->id();
        if (! $institutionId) {
            return;
        }

        $column = $model->qualifyColumn('institution_id');
        if ($model instanceof User) {
            $builder->where(fn (Builder $query) => $query
                ->where($column, $institutionId)
                ->orWhere($model->qualifyColumn('is_system_admin'), true));

            return;
        }

        $builder->where($column, $institutionId);
    }
}
