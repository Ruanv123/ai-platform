<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmFieldVisibilityController extends Controller
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
        $fieldIds = collect($integration->crm_metadata['fields'] ?? [])
            ->filter(fn (array $field): bool => ($field['entity'] ?? null) === 'contact')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
        $validated = $request->validate([
            'visible_field_ids' => ['present', 'array', 'max:100'],
            'visible_field_ids.*' => ['required', 'string', 'distinct', Rule::in($fieldIds)],
        ]);

        $integration->forceFill([
            'crm_settings' => [
                ...($integration->crm_settings ?? []),
                'agent_visible_field_ids' => array_values($validated['visible_field_ids']),
            ],
        ])->save();

        return to_route('integrations.show', 'crm')->with('crm_success', 'Campos visíveis ao Agente atualizados.');
    }
}
