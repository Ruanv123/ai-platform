<?php

namespace App\Http\Controllers\Agents;

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\CreateAgentRequest;
use App\Http\Requests\Agents\SaveAgentRequest;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Collection;
use App\Models\Integration;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    /**
     * Display the current Empresa's Agents.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('agents/index', [
            'agents' => $team->agents()
                ->withCount(['collections', 'channels'])
                ->with(['collections', 'schedulableCalendars', 'channels' => fn ($query) => $query->orderBy('id')])
                ->orderBy('name')
                ->get()
                ->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'objective' => $agent->objective->value,
                    'objectiveLabel' => $agent->objective->label(),
                    'tone' => $agent->tone->value,
                    'toneLabel' => $agent->tone->label(),
                    'usesEmojis' => $agent->uses_emojis,
                    'responseLength' => $agent->response_length->value,
                    'responseLengthLabel' => $agent->response_length->label(),
                    'welcomeMessage' => $agent->welcome_message,
                    'instructions' => $agent->instructions,
                    'rules' => $agent->rules,
                    'isActive' => $agent->is_active,
                    'collectionIds' => $agent->collections->modelKeys(),
                    'schedulableCalendarIds' => $agent->schedulableCalendars->modelKeys(),
                    'collectionCount' => $agent->collections_count,
                    'channelCount' => $agent->channels_count,
                    'channels' => $agent->channels
                        ->map(fn (Channel $channel): array => [
                            'id' => $channel->id,
                            'type' => $channel->type->value,
                            'typeLabel' => $channel->type->label(),
                            'status' => $channel->status->value,
                            'statusLabel' => $channel->status->label(),
                        ])
                        ->values(),
                ])
                ->values(),
            'collections' => $team->collections()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Collection $collection): array => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                ])
                ->values(),
            'schedulableCalendars' => $team->schedulableCalendars()
                ->whereHas('integration', fn ($query) => $query->where('type', 'agenda'))
                ->orderBy('display_name')
                ->get()
                ->map(fn (SchedulableCalendar $calendar): array => [
                    'id' => $calendar->id,
                    'name' => $calendar->display_name,
                    'description' => $calendar->display_description,
                ])
                ->values(),
            'agendaConnected' => $team->integrations()->where('type', 'agenda')->where('status', 'connected')->exists(),
            'crmConnected' => $team->integrations()->where('type', 'crm')->where('status', 'connected')->exists(),
            'permissions' => $user->toTeamPermissions($team),
            'objectives' => AgentObjective::options(),
            'tones' => AgentTone::options(),
            'responseLengths' => AgentResponseLength::options(),
        ]);
    }

    /**
     * Show the editor for an existing Agent.
     */
    public function edit(Request $request, Agent $agent): Response
    {
        Gate::authorize('view', $agent);

        $agent->loadMissing(['collections', 'schedulableCalendars']);

        return Inertia::render('agents/edit', $this->editorProps(
            $agent->team,
            $agent,
            Gate::allows('update', $agent),
            $request->query('panel') === 'playground' ? 'playground' : 'config',
        ));
    }

    /**
     * Quickly create a new Agent from a name and description.
     *
     * The remaining configuration is filled in afterwards on the editor.
     */
    public function store(CreateAgentRequest $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $agent = $team->agents()->create([
            ...$request->validated(),
            'objective' => AgentObjective::Support,
            'tone' => AgentTone::Friendly,
            'uses_emojis' => false,
            'response_length' => AgentResponseLength::Short,
            'welcome_message' => 'Olá! Como posso ajudar você hoje?',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente criado com sucesso.',
        ]);

        return to_route('agents.edit', $agent);
    }

    /**
     * Update an Agent.
     */
    public function update(SaveAgentRequest $request, Agent $agent): RedirectResponse
    {
        $this->saveDraft($agent, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente atualizado com sucesso.',
        ]);

        return to_route('agents.edit', $agent);
    }

    public function publish(SaveAgentRequest $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($agent, $validated): void {
            $lockedAgent = $agent->newQuery()
                ->lockForUpdate()
                ->whereKey($agent->getKey())
                ->firstOrFail();

            $this->saveDraft($lockedAgent, $validated);
            $number = ((int) $lockedAgent->versions()->max('number')) + 1;

            $lockedAgent->versions()->create([
                'team_id' => $lockedAgent->team_id,
                'number' => $number,
                'objective' => $lockedAgent->objective,
                'ecommerce_tools' => [
                    'search_products' => $lockedAgent->search_products,
                    'send_purchase_link' => $lockedAgent->send_purchase_link,
                ],
                'crm_tools' => [
                    'ensure_deal_enabled' => $lockedAgent->crm_ensure_deal_enabled ?? in_array($lockedAgent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true),
                    'move_deal_enabled' => $lockedAgent->crm_move_deal_enabled ?? in_array($lockedAgent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true),
                    'register_note_enabled' => $lockedAgent->crm_register_note_enabled ?? in_array($lockedAgent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true),
                    'pipeline_id' => $lockedAgent->crm_pipeline_id,
                    'initial_stage_id' => $lockedAgent->crm_initial_stage_id,
                    'allowed_stages' => $lockedAgent->crm_allowed_stages ?? [],
                ],
                'published_at' => now(),
            ]);

            $lockedAgent->forceFill(['shop_tools_enabled' => true])->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Versão do Agente publicada para os Canais.',
        ]);

        return to_route('agents.edit', $agent);
    }

    /**
     * Toggle an Agent's active status.
     */
    public function toggle(Agent $agent): RedirectResponse
    {
        Gate::authorize('update', $agent);

        $agent->update(['is_active' => ! $agent->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $agent->is_active
                ? 'Agente ativado com sucesso.'
                : 'Agente desativado com sucesso.',
        ]);

        return to_route('agents.index');
    }

    /**
     * Delete an Agent.
     */
    public function destroy(Agent $agent): RedirectResponse
    {
        Gate::authorize('delete', $agent);

        $agent->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente excluído com sucesso.',
        ]);

        return to_route('agents.index');
    }

    /**
     * Get the props shared by the editor screen.
     *
     * @return array<string, mixed>
     */
    private function editorProps(
        Team $team,
        Agent $agent,
        bool $canManageAgents = false,
        string $initialPanel = 'config',
    ): array {
        $shopConnected = $team->shops()->exists();
        $crmIntegration = $team->integrations()->where('type', 'crm')->where('status', 'connected')->first();
        $crmConnected = $crmIntegration !== null;
        $crmPipelines = $this->crmPipelineOptions($crmIntegration);
        $firstPipeline = $crmPipelines[0] ?? null;
        $crmPipelineId = $agent->crm_pipeline_id ?? ($firstPipeline['id'] ?? '');
        $selectedPipeline = collect($crmPipelines)->firstWhere('id', $crmPipelineId);
        $crmInitialStageId = $agent->crm_initial_stage_id;
        if (! collect($selectedPipeline['stages'] ?? [])->contains('id', $crmInitialStageId)) {
            $crmInitialStageId = $selectedPipeline['stages'][0]['id'] ?? '';
        }
        $crmAllowedStages = collect($agent->crm_allowed_stages ?? [])
            ->filter(fn (array $allowedStage): bool => collect($selectedPipeline['stages'] ?? [])->contains(
                fn (array $stage): bool => $stage['id'] === ($allowedStage['stage_id'] ?? null)
                    && ! in_array(strtolower((string) ($stage['status'] ?? '')), ['won', 'lost'], true),
            ))
            ->values()
            ->all();
        $crmActionsDefaultEnabled = $crmConnected && in_array($agent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true);
        $publishedVersion = $agent->publishedVersion();

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'objective' => $agent->objective->value,
                'searchProducts' => $agent->search_products ?? ($shopConnected && $agent->objective === AgentObjective::Sales),
                'sendPurchaseLink' => $agent->send_purchase_link ?? ($shopConnected && $agent->objective === AgentObjective::Sales),
                'tone' => $agent->tone->value,
                'usesEmojis' => $agent->uses_emojis,
                'responseLength' => $agent->response_length->value,
                'welcomeMessage' => $agent->welcome_message,
                'instructions' => $agent->instructions,
                'rules' => $agent->rules,
                'collectionIds' => $agent->collections->modelKeys(),
                'schedulableCalendarIds' => $agent->schedulableCalendars->modelKeys(),
                'crmLookupEnabled' => $agent->crm_lookup_enabled ?? ($crmConnected && in_array($agent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true)),
                'crmLookupConfigured' => $agent->crm_lookup_enabled !== null,
                'crmEnsureDealEnabled' => $agent->crm_ensure_deal_enabled ?? ($crmConnected && in_array($agent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true)),
                'crmEnsureDealConfigured' => $agent->crm_ensure_deal_enabled !== null,
                'crmMoveDealEnabled' => $agent->crm_move_deal_enabled ?? $crmActionsDefaultEnabled,
                'crmMoveDealConfigured' => $agent->crm_move_deal_enabled !== null,
                'crmRegisterNoteEnabled' => $agent->crm_register_note_enabled ?? $crmActionsDefaultEnabled,
                'crmRegisterNoteConfigured' => $agent->crm_register_note_enabled !== null,
                'crmAllowedStages' => $crmAllowedStages,
                'crmPipelineId' => $crmPipelineId,
                'crmInitialStageId' => $crmInitialStageId,
            ],
            'initialPanel' => $initialPanel,
            'collections' => $team->collections()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Collection $collection): array => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                ])
                ->values(),
            'schedulableCalendars' => $team->schedulableCalendars()
                ->whereHas('integration', fn ($query) => $query->where('type', 'agenda'))
                ->orderBy('display_name')
                ->get()
                ->map(fn (SchedulableCalendar $calendar): array => [
                    'id' => $calendar->id,
                    'name' => $calendar->display_name,
                    'description' => $calendar->display_description,
                ])
                ->values(),
            'agendaConnected' => $team->integrations()->where('type', 'agenda')->where('status', 'connected')->exists(),
            'crmConnected' => $crmConnected,
            'crmPipelines' => $crmPipelines,
            'shopConnected' => $shopConnected,
            'productSearchConfigured' => $agent->search_products !== null,
            'purchaseLinkConfigured' => $agent->send_purchase_link !== null,
            'publishedVersion' => $publishedVersion === null ? null : [
                'number' => $publishedVersion->number,
            ],
            'objectives' => AgentObjective::options(),
            'tones' => AgentTone::options(),
            'responseLengths' => AgentResponseLength::options(),
            'permissions' => [
                'canManageAgents' => $canManageAgents,
            ],
        ];
    }

    private function saveDraft(Agent $agent, array $validated): void
    {
        $collectionIds = $validated['collection_ids'] ?? [];
        $calendarIds = $validated['schedulable_calendar_ids'] ?? [];
        unset($validated['collection_ids'], $validated['schedulable_calendar_ids']);

        if (array_key_exists('crm_pipeline_id', $validated)) {
            $validated['crm_allowed_stages'] ??= [];
        }

        $objective = $validated['objective'] ?? $agent->objective->value;
        $crmIntegration = $agent->team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->first();
        if ($crmIntegration !== null && in_array($objective, [AgentObjective::Sales->value, AgentObjective::Qualification->value], true)) {
            $pipelines = $crmIntegration->crm_metadata['pipelines'] ?? [];
            $firstPipeline = $pipelines[0] ?? null;
            $pipelineId = $validated['crm_pipeline_id'] ?? $agent->crm_pipeline_id ?? ($firstPipeline['id'] ?? null);
            $selectedPipeline = collect($pipelines)->first(fn (array $pipeline): bool => (string) ($pipeline['id'] ?? '') === (string) $pipelineId);
            $firstStageId = collect($selectedPipeline['stages'] ?? [])
                ->first(fn (array $stage): bool => ! in_array(strtolower((string) ($stage['status'] ?? '')), ['won', 'lost'], true))['id'] ?? null;

            foreach (['crm_lookup_enabled', 'crm_ensure_deal_enabled', 'crm_move_deal_enabled', 'crm_register_note_enabled'] as $action) {
                if (! array_key_exists($action, $validated) && $agent->{$action} === null) {
                    $validated[$action] = true;
                }
            }

            if (! array_key_exists('crm_pipeline_id', $validated) && $agent->crm_pipeline_id === null) {
                $validated['crm_pipeline_id'] = is_string($pipelineId) ? $pipelineId : null;
            }
            if (! array_key_exists('crm_initial_stage_id', $validated) && $agent->crm_initial_stage_id === null) {
                $validated['crm_initial_stage_id'] = is_string($firstStageId) ? $firstStageId : null;
            }
        }

        $agent->update($validated);
        $agent->collections()->sync($collectionIds);
        $agent->schedulableCalendars()->sync($calendarIds);
    }

    /**
     * @return list<array{id: string, name: string, stages: list<array{id: string, name: string, status: ?string, commercial: bool}>}>
     */
    private function crmPipelineOptions(?Integration $integration): array
    {
        return collect($integration?->crm_metadata['pipelines'] ?? [])
            ->filter(fn (array $pipeline): bool => is_string($pipeline['id'] ?? null))
            ->map(fn (array $pipeline): array => [
                'id' => $pipeline['id'],
                'name' => (string) ($pipeline['name'] ?? $pipeline['id']),
                'stages' => collect($pipeline['stages'] ?? [])
                    ->filter(fn (array $stage): bool => is_string($stage['id'] ?? null))
                    ->map(fn (array $stage): array => [
                        'id' => $stage['id'],
                        'name' => (string) ($stage['name'] ?? $stage['id']),
                        'status' => is_string($stage['status'] ?? null) ? $stage['status'] : null,
                        'commercial' => in_array($stage['id'], data_get($integration?->crm_settings, 'commercial_stage_ids', []), true),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
