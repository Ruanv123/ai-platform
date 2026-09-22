<?php

namespace App\Http\Controllers\Events;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\SaveFlowEventRequest;
use App\Models\Flow;
use App\Models\FlowEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FlowEventController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->company();

        return Inertia::render('integrations/events', [
            'events' => $team->flowEvents()->with('webhooks')->withCount('emissions')->latest('name')->get()->map(fn (FlowEvent $event): array => $this->serialize($event))->values(),
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function store(SaveFlowEventRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validated();
        $event = $request->user()->company()->flowEvents()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'payload_schema' => $data['payload_schema'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($event), 201);
        }

        return to_route('integrations.index');
    }

    public function update(SaveFlowEventRequest $request, FlowEvent $flowEvent): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $flowEvent);
        $flowEvent->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json($this->serialize($flowEvent->fresh(['webhooks'])));
        }

        return to_route('integrations.index');
    }

    public function destroy(Request $request, FlowEvent $flowEvent): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $flowEvent);
        $usingFlows = $this->flowsUsing($request, $flowEvent->getKey());
        if ($usingFlows->isNotEmpty()) {
            throw ValidationException::withMessages([
                'event' => 'Não é possível apagar este Evento. Ele é emitido por: '.$usingFlows->join(', ').'.',
            ]);
        }
        if ($flowEvent->emissions()->exists()) {
            throw ValidationException::withMessages([
                'event' => 'Este Evento já possui histórico emitido e não pode ser apagado. Renomeie-o ou mantenha-o no catálogo.',
            ]);
        }

        $flowEvent->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return to_route('integrations.index');
    }

    /** @return Collection<int, string> */
    private function flowsUsing(Request $request, string $eventId): Collection
    {
        return $request->user()->company()->flows()->with('versions')->get(['id', 'name', 'draft_dsl'])->filter(function (Flow $flow) use ($eventId): bool {
            $definitions = [$flow->draft_dsl, ...$flow->versions->pluck('dsl')->all()];
            foreach ($definitions as $dsl) {
                foreach ((array) data_get($dsl, 'nodes', []) as $node) {
                    if ((string) data_get($node, 'config.event_id') === $eventId) {
                        return true;
                    }
                }
            }

            return false;
        })->pluck('name')->values();
    }

    /** @return array<string, mixed> */
    private function serialize(FlowEvent $event): array
    {
        return [
            'id' => $event->getKey(),
            'name' => $event->name,
            'description' => $event->description,
            'payloadSchema' => $event->payload_schema,
            'emissionsCount' => (int) ($event->emissions_count ?? $event->emissions()->count()),
            'webhooks' => $event->webhooks->map(fn ($webhook): array => [
                'id' => $webhook->getKey(),
                'url' => $webhook->url,
                'active' => $webhook->active,
            ])->values(),
        ];
    }

    private function authorizeManagement(Request $request, ?FlowEvent $flowEvent = null): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        if ($flowEvent !== null) {
            abort_unless($flowEvent->team_id === $team->getKey(), 404);
        }
    }
}
