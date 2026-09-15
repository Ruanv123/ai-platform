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

        abort_if($membership->role === TeamRole::Owner, 403, 'O papel do proprietário da Empresa não pode ser alterado.');

        $newRole = TeamRole::from($request->validated('role'));

        $membership->update(['role' => $newRole]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Papel do membro atualizado.']);

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

        abort_if($team->owner()?->is($user), 403, 'O proprietário da Empresa não pode ser removido.');

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->update(['current_team_id' => null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Membro removido.']);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Você saiu da Empresa.']);

        return to_route('onboarding.company');
    }
}
