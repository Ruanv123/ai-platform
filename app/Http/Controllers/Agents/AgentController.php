<?php

namespace App\Http\Controllers\Agents;

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\SaveAgentRequest;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Collection;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    /**
     * Show the editor for a new Agent.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('create', Agent::class);

        return Inertia::render('agents/edit', $this->editorProps($team));
    }

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
                ->with(['collections', 'channels'])
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
            'permissions' => $user->toTeamPermissions($team),
            'objectives' => AgentObjective::options(),
            'tones' => AgentTone::options(),
            'responseLengths' => AgentResponseLength::options(),
        ]);
    }

    /**
     * Show the editor for an existing Agent.
     */
    public function edit(Agent $agent): Response
    {
        Gate::authorize('update', $agent);

        $agent->loadMissing('collections');

        return Inertia::render('agents/edit', $this->editorProps($agent->team, $agent));
    }

    /**
     * Store a new Agent.
     */
    public function store(SaveAgentRequest $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $validated = $request->validated();
        $collectionIds = $validated['collection_ids'] ?? [];
        unset($validated['collection_ids']);

        $agent = $team->agents()->create($validated);
        $agent->collections()->sync($collectionIds);

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
        $validated = $request->validated();
        $collectionIds = $validated['collection_ids'] ?? [];
        unset($validated['collection_ids']);

        $agent->update($validated);
        $agent->collections()->sync($collectionIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente atualizado com sucesso.',
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
     * Get the props shared by the create and edit screens.
     *
     * @return array<string, mixed>
     */
    private function editorProps(Team $team, ?Agent $agent = null): array
    {
        return [
            'agent' => $agent === null ? null : [
                'id' => $agent->id,
                'name' => $agent->name,
                'objective' => $agent->objective->value,
                'tone' => $agent->tone->value,
                'usesEmojis' => $agent->uses_emojis,
                'responseLength' => $agent->response_length->value,
                'welcomeMessage' => $agent->welcome_message,
                'instructions' => $agent->instructions,
                'rules' => $agent->rules,
                'collectionIds' => $agent->collections->modelKeys(),
            ],
            'collections' => $team->collections()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Collection $collection): array => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                ])
                ->values(),
            'objectives' => AgentObjective::options(),
            'tones' => AgentTone::options(),
            'responseLengths' => AgentResponseLength::options(),
        ];
    }
}
