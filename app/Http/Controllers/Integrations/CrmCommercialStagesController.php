<?php

namespace App\Http\Controllers\Integrations;

use App\Crm\CrmCommercialStageService;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmCommercialStagesController extends Controller
{
    public function update(Request $request, CrmCommercialStageService $commercialStages): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->firstOrFail();
        $stageIds = collect($integration->crm_metadata['pipelines'] ?? [])
            ->flatMap(fn (array $pipeline): array => $pipeline['stages'] ?? [])
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
        $validated = $request->validate([
            'commercial_stage_ids' => ['present', 'array', 'max:200'],
            'commercial_stage_ids.*' => ['required', 'string', 'distinct', Rule::in($stageIds)],
        ]);

        $commercialStages->updateCommercialStages($integration, $validated['commercial_stage_ids']);

        return to_route('integrations.show', 'crm')->with('crm_success', 'Etapas comerciais atualizadas.');
    }
}
