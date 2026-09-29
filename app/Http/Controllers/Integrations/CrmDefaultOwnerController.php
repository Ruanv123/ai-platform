<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmDefaultOwnerController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->firstOrFail();
        $ownerIds = collect($integration->crm_metadata['owners'] ?? [])
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
        $validated = $request->validate([
            'default_owner_id' => ['present', 'nullable', 'string', Rule::in($ownerIds)],
        ]);

        $integration->forceFill([
            'crm_settings' => [
                ...($integration->crm_settings ?? []),
                'default_owner_id' => $validated['default_owner_id'],
            ],
        ])->save();

        return to_route('integrations.show', 'crm')->with('crm_success', 'Responsável padrão atualizado.');
    }
}
