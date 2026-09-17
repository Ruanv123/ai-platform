<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Show the current team's members and pending invitations.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $team = $user->company();

        abort_if(! $team, 404);

        $permissions = $user->toTeamPermissions($team);
        $props = [
            'members' => $team->members()->get()->map(function (User $member): array {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar ?? null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            }),
            'permissions' => $permissions,
            'canLeave' => Gate::forUser($user)->allows('leave', $team),
            'availableRoles' => TeamRole::assignable(),
        ];

        if ($permissions->canCreateInvitation || $permissions->canCancelInvitation) {
            $props['invitations'] = $team->invitations()
                ->pending()
                ->latest()
                ->get()
                ->map(fn (TeamInvitation $invitation): array => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]);
        }

        return Inertia::render('settings/team', $props);
    }
}
