<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
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
    public function view(User $user, Channel $channel): bool
    {
        return $channel->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Channel $channel): bool
    {
        return $this->canManageChannels($user)
            && $channel->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Channel $channel): bool
    {
        return $this->canManageChannels($user)
            && $channel->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Channel $channel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Channel $channel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage Channels in their Empresa.
     */
    private function canManageChannels(User $user): bool
    {
        $team = $user->company();

        return $team !== null
            && $user->hasTeamPermission($team, TeamPermission::ManageChannels);
    }
}
