<?php

namespace App\Http\Controllers\Integrations;

use App\Crm\CrmFieldMappingService;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\ContactField;
use App\Models\CrmFieldMapping;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmFieldMappingController extends Controller
{
    public function store(Request $request, CrmFieldMappingService $fieldMappings): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->firstOrFail();

        return $this->persist($request, $team->getKey(), $integration, null, $fieldMappings);
    }

    public function update(
        Request $request,
        CrmFieldMapping $crmFieldMapping,
        CrmFieldMappingService $fieldMappings,
    ): RedirectResponse {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        abort_unless($crmFieldMapping->team_id === $team->getKey(), 404);

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->firstOrFail();
        abort_unless($crmFieldMapping->integration_id === $integration->getKey(), 404);

        return $this->persist($request, $team->getKey(), $integration, $crmFieldMapping, $fieldMappings);
    }

    public function destroy(Request $request, CrmFieldMapping $crmFieldMapping): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        abort_unless($crmFieldMapping->team_id === $team->getKey(), 404);

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->firstOrFail();
        abort_unless($crmFieldMapping->integration_id === $integration->getKey(), 404);

        $crmFieldMapping->delete();

        return to_route('integrations.show', 'crm')->with('crm_success', 'Mapeamento de Campo removido.');
    }

    private function persist(
        Request $request,
        int $teamId,
        Integration $integration,
        ?CrmFieldMapping $crmFieldMapping,
        CrmFieldMappingService $fieldMappings,
    ): RedirectResponse {
        $contactFieldRule = Rule::unique('crm_field_mappings', 'contact_field_id')
            ->where('team_id', $teamId)
            ->where('integration_id', $integration->getKey());
        $crmFieldRule = Rule::unique('crm_field_mappings', 'crm_field_id')
            ->where('team_id', $teamId)
            ->where('integration_id', $integration->getKey());
        if ($crmFieldMapping !== null) {
            $contactFieldRule->ignore($crmFieldMapping->getKey());
            $crmFieldRule->ignore($crmFieldMapping->getKey());
        }

        $validated = $request->validate([
            'contact_field_id' => [
                'required',
                'string',
                Rule::exists('contact_fields', 'id')->where('team_id', $teamId),
                $contactFieldRule,
            ],
            'crm_field_id' => [
                'required',
                'string',
                $crmFieldRule,
            ],
            'direction' => ['required', Rule::in(['to_crm', 'from_crm'])],
            'option_mappings' => ['sometimes', 'array', 'max:100'],
            'option_mappings.*' => ['required', 'string', 'distinct'],
        ]);

        $contactField = ContactField::query()
            ->where('team_id', $teamId)
            ->whereKey($validated['contact_field_id'])
            ->firstOrFail();
        $crmField = collect($integration->crm_metadata['fields'] ?? [])
            ->first(fn (array $field): bool => (string) ($field['id'] ?? '') === $validated['crm_field_id']);
        if (! is_array($crmField)) {
            throw ValidationException::withMessages([
                'crm_field_id' => 'O Campo do CRM selecionado não está mais disponível.',
            ]);
        }

        if (! $fieldMappings->isCompatible($contactField, $crmField)) {
            throw ValidationException::withMessages([
                'crm_field_id' => 'Os tipos dos Campos do Contato e do CRM não são compatíveis.',
            ]);
        }

        $optionMappings = $validated['option_mappings'] ?? [];
        if (! $fieldMappings->hasCompleteOptionMapping($contactField, $crmField, $optionMappings)) {
            throw ValidationException::withMessages([
                'option_mappings' => 'Mapeie cada opção do Campo do Contato para uma opção diferente do Campo do CRM.',
            ]);
        }

        $mapping = $crmFieldMapping ?? new CrmFieldMapping;
        $mapping->forceFill([
            'team_id' => $teamId,
            'integration_id' => $integration->getKey(),
            'contact_field_id' => $contactField->getKey(),
            'crm_field_id' => $validated['crm_field_id'],
            'direction' => $validated['direction'],
            'option_mappings' => $contactField->type === 'choice' ? $optionMappings : null,
        ])->save();

        $fieldMappings->queueExistingValuesForMapping($mapping);

        return to_route('integrations.show', 'crm')->with('crm_success', 'Mapeamento de Campos atualizado.');
    }
}
