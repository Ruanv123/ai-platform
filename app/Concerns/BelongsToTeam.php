<?php

namespace App\Concerns;

use App\Models\Scopes\TeamScope;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTeam
{
    /**
     * Bootstrap the team ownership behavior.
     */
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new TeamScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('team_id') !== null) {
                return;
            }

            if (! app()->bound('request')) {
                return;
            }

            $team = request()->user()?->company();

            if ($team !== null) {
                $model->setAttribute('team_id', $team->getKey());
            }
        });
    }

    /**
     * Get the Empresa that owns the model.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
