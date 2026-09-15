<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTeam
{
    /**
     * Create an Empresa and attach its owner.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, string $name, array $attributes = []): Team
    {
        Gate::forUser($user)->authorize('create', Team::class);

        return DB::transaction(function () use ($user, $name, $attributes) {
            $team = Team::create([
                ...$attributes,
                'name' => $name,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $team->collections()->create([
                'name' => 'Geral',
                'is_default' => true,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
