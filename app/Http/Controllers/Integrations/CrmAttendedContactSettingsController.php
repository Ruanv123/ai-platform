<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrmAttendedContactSettingsController extends Controller
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
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'minimum_messages' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $integration->forceFill([
            'crm_settings' => [
                ...($integration->crm_settings ?? []),
                'register_attended_contacts' => (bool) $validated['enabled'],
                'minimum_attended_contact_messages' => (int) $validated['minimum_messages'],
            ],
        ])->save();

        return to_route('integrations.show', 'crm')->with('crm_success', 'Registro de Contatos atendidos atualizado.');
    }
}
