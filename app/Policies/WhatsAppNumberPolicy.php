<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\User;
use App\Models\WhatsAppNumber;

class WhatsAppNumberPolicy
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
    public function view(User $user, WhatsAppNumber $whatsAppNumber): bool
    {
        return $whatsAppNumber->team_id === $user->company()?->getKey();
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
    public function update(User $user, WhatsAppNumber $whatsAppNumber): bool
    {
        return $this->canManageChannels($user)
            && $whatsAppNumber->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WhatsAppNumber $whatsAppNumber): bool
    {
        return $this->canManageChannels($user)
            && $whatsAppNumber->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WhatsAppNumber $whatsAppNumber): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WhatsAppNumber $whatsAppNumber): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage channels in their Empresa.
     */
    private function canManageChannels(User $user): bool
    {
        $team = $user->company();

        return $team !== null
            && $user->hasTeamPermission($team, TeamPermission::ManageChannels);
    }
}
