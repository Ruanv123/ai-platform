<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Enums\TeamSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateCompanyRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Show the current team settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $team = $user->company();

        abort_if(! $team, 404);

        return Inertia::render($request->routeIs('company.edit') ? 'settings/company' : 'teams/edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'segment' => $team->segment,
                'website' => $team->website,
                'businessDescription' => $team->business_description,
                'productsServices' => $team->products_services,
                'targetAudience' => $team->target_audience,
                'phone' => $team->phone,
                'publicEmail' => $team->public_email,
                'address' => $team->address,
            ],
            'members' => $team->members()->get()->map(function (User $member) {
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
            'invitations' => $team->invitations()
                ->pending()
                ->latest()
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toTeamPermissions($team),
            'canLeave' => Gate::forUser($user)->allows('leave', $team),
            'availableRoles' => TeamRole::assignable(),
            'segments' => TeamSegment::options(),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);
        Gate::authorize('update', $team);

        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dados da Empresa atualizados.']);

        return to_route($request->routeIs('company.update') ? 'company.edit' : 'teams.edit');
    }
}
