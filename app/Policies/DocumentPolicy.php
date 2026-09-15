<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
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
    public function view(User $user, Document $document): bool
    {
        return $document->team_id === $user->company()?->getKey();
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
    public function update(User $user, Document $document): bool
    {
        return $this->canManageKnowledge($user)
            && $document->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $this->canManageKnowledge($user)
            && $document->team_id === $user->company()?->getKey();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Document $document): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    private function canManageKnowledge(User $user): bool
    {
        $team = $user->company();

        return $team !== null
            && $user->hasTeamPermission($team, TeamPermission::ManageKnowledge);
    }
}
