<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->company() !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Collection $collection): bool
    {
        return $user->belongsToTeam($collection->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManageKnowledge($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Collection $collection): bool
    {
        return $this->canManageKnowledge($user)
            && $collection->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Collection $collection): bool
    {
        return ! $collection->is_default
            && $this->canManageKnowledge($user)
            && $collection->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Collection $collection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Collection $collection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage knowledge in their Empresa.
     */
    private function canManageKnowledge(User $user): bool
    {
        $team = $user->company();

        return $team !== null
            && $user->hasTeamPermission($team, TeamPermission::ManageKnowledge);
    }
}
