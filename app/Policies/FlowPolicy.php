<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Flow;
use App\Models\User;

class FlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->company() !== null;
    }

    public function view(User $user, Flow $flow): bool
    {
        return $flow->team_id === $user->company()?->getKey();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Flow $flow): bool
    {
        return $this->canManage($user) && $this->view($user, $flow);
    }

    public function publish(User $user, Flow $flow): bool
    {
        return $this->canManage($user) && $this->view($user, $flow);
    }

    public function pause(User $user, Flow $flow): bool
    {
        return $this->canManage($user) && $this->view($user, $flow);
    }

    public function delete(User $user, Flow $flow): bool
    {
        return $this->canManage($user) && $this->view($user, $flow);
    }

    private function canManage(User $user): bool
    {
        $team = $user->company();

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageFlows);
    }
}
