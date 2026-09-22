<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\CrmProvider;
use App\Enums\EcommerceProvider;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\SaveIntegrationRequest;
use App\Integrations\IntegrationConnectionTester;
use App\Models\Flow;
use App\Models\FlowEvent;
use App\Models\FlowEventDelivery;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->company();
        $team->load([
            'integrations',
            'flowEvents' => fn ($query) => $query->withCount('emissions')->with('webhooks'),
        ]);

        return Inertia::render('integrations/index', [
            'integrations' => $this->catalog(CrmProvider::catalog()),
            'ecommerceIntegrations' => $this->catalog(EcommerceProvider::catalog()),
            'agendaIntegrations' => [[
                'value' => 'agenda',
                'name' => 'Agenda',
                'description' => 'Consulte horários e crie compromissos direto no atendimento.',
                'logo' => 'AG',
                'capabilities' => ['Consultar disponibilidade', 'Criar compromisso'],
                'available' => true,
            ]],
            'configuredIntegrations' => $team->integrations->map(fn (Integration $integration): array => $this->serialize($integration))->values(),
            'events' => $team->flowEvents->map(fn (FlowEvent $event): array => $this->serializeEvent($event))->values(),
            'webhookFailures' => [
                'count' => $this->failedDeliveryCount($team),
                'href' => route('integrations.webhook-failures.index'),
            ],
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function store(SaveIntegrationRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request);
        $team = $request->user()->company();
        $data = $request->validated();
        $integration = $team->integrations()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'provider' => $data['provider'] ?? null,
            'credentials' => $this->credentials($data),
            'status' => 'never_tested',
        ]);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($integration), 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integração cadastrada. Teste a conexão para ativá-la.']);

        return to_route('integrations.index');
    }

    public function update(SaveIntegrationRequest $request, Integration $integration): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $integration);
        $data = $request->validated();
        $integration->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'provider' => $data['provider'] ?? null,
            'credentials' => $this->credentials($data, $integration->credentials),
            'status' => 'never_tested',
            'status_message' => null,
            'last_tested_at' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($integration->fresh()));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integração atualizada. Teste a conexão novamente.']);

        return to_route('integrations.index');
    }

    public function test(Request $request, Integration $integration, IntegrationConnectionTester $tester): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $integration);
        $result = $tester->test($integration);
        $integration->forceFill([
            'status' => $result['connected'] ? 'connected' : 'error',
            'status_message' => $result['message'],
            'last_tested_at' => now(),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'integration' => $this->serialize($integration->fresh()),
                'message' => $result['message'],
            ], $result['connected'] ? 200 : 422);
        }

        Inertia::flash('toast', [
            'type' => $result['connected'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return to_route('integrations.index');
    }

    public function destroy(Request $request, Integration $integration): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $integration);
        $usingFlows = $this->flowsUsing($request->user()->company(), $integration->getKey());
        if ($usingFlows->isNotEmpty()) {
            $message = 'Não é possível apagar esta Integração porque ela é usada por: '.$usingFlows->join(', ').'. Remova-a dos Fluxos antes de continuar.';
            throw ValidationException::withMessages(['integration' => $message]);
        }

        $integration->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integração apagada.']);

        return to_route('integrations.index');
    }

    /** @param list<array<string, mixed>> $catalog */
    private function catalog(array $catalog): array
    {
        return array_map(fn (array $item): array => [...$item, 'planned' => true, 'available' => false], $catalog);
    }

    /** @param array<string, mixed> $data */
    private function credentials(array $data, array $existing = []): array
    {
        $credentials = is_array($data['credentials'] ?? null) ? $data['credentials'] : $existing;
        foreach (['base_url', 'auth_type', 'token', 'api_key', 'api_key_header', 'username', 'password'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $credentials[$key] = $data[$key];
            }
        }

        return $credentials;
    }

    /** @return Collection<int, Flow> */
    private function flowsUsing(Team $team, string $integrationId): Collection
    {
        return $team->flows()->with('versions')->get(['id', 'name', 'draft_dsl'])->filter(function (Flow $flow) use ($integrationId): bool {
            $definitions = [$flow->draft_dsl, ...$flow->versions->pluck('dsl')->all()];

            foreach ($definitions as $dsl) {
                foreach ((array) data_get($dsl, 'nodes', []) as $node) {
                    if ((string) data_get($node, 'config.integration_id') === $integrationId) {
                        return true;
                    }
                }
            }

            return false;
        })->values();
    }

    private function failedDeliveryCount(Team $team): int
    {
        return FlowEventDelivery::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->where('status', 'failed')
            ->count();
    }

    /** @return array<string, mixed> */
    private function serialize(Integration $integration): array
    {
        return [
            'id' => $integration->getKey(),
            'name' => $integration->name,
            'type' => $integration->type,
            'provider' => $integration->provider,
            'baseUrl' => $integration->baseUrl(),
            'status' => $integration->status,
            'statusLabel' => match ($integration->status) {
                'connected' => 'Conectada',
                'error' => 'Com erro',
                default => 'Nunca testada',
            },
            'statusMessage' => $integration->status_message,
            'lastTestedAt' => $integration->last_tested_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeEvent(FlowEvent $event): array
    {
        return [
            'id' => $event->getKey(),
            'name' => $event->name,
            'description' => $event->description,
            'payloadSchema' => $event->payload_schema,
            'webhooks' => $event->webhooks->map(fn ($webhook): array => [
                'id' => $webhook->getKey(),
                'url' => $webhook->url,
                'active' => $webhook->active,
            ])->values(),
            'emissionsCount' => (int) ($event->emissions_count ?? 0),
        ];
    }

    private function authorizeManagement(Request $request, ?Integration $integration = null): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        if ($integration !== null) {
            abort_unless($integration->team_id === $team->getKey(), 404);
        }
    }
}
