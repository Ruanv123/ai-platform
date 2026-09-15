<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @implements Scope<Model>
 */
class TeamScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('request') || ! Auth::check()) {
            return;
        }

        $team = Auth::user()?->company();

        if ($team === null) {
            $builder->whereNull($model->qualifyColumn('team_id'));

            return;
        }

        $builder->where($model->qualifyColumn('team_id'), $team->getKey());
    }
}
