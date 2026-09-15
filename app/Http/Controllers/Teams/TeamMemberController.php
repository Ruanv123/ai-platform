<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, User $user): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);
        Gate::authorize('updateMember', $team);

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Owner, 403, __('The team owner role cannot be changed.'));

        $newRole = TeamRole::from($request->validated('role'));

        $membership->update(['role' => $newRole]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit');
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);
        Gate::authorize('removeMember', $team);

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->update(['current_team_id' => null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit');
    }

    /**
     * Leave the current team as a non-owner member.
     */
    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->company();

        abort_if(! $team, 404);
        Gate::forUser($user)->authorize('leave', $team);

        DB::transaction(function () use ($user, $team): void {
            $team->memberships()->where('user_id', $user->id)->delete();
            $user->update(['current_team_id' => null]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the Empresa.')]);

        return to_route('onboarding.company');
    }
}
