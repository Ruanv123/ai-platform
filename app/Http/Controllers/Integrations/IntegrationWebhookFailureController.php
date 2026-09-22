<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverFlowEventWebhook;
use App\Models\FlowEventDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationWebhookFailureController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->company();
        $failures = FlowEventDelivery::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->where('status', 'failed')
            ->with(['event', 'webhook'])
            ->latest('last_attempt_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('integrations/webhook-failures', [
            'failures' => [
                'data' => $failures->getCollection()->map(fn (FlowEventDelivery $delivery): array => $this->serialize($delivery))->values(),
                'currentPage' => $failures->currentPage(),
                'lastPage' => $failures->lastPage(),
                'total' => $failures->total(),
            ],
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function retry(Request $request, FlowEventDelivery $delivery): RedirectResponse|JsonResponse
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        abort_unless($delivery->team_id === $team->getKey(), 404);
        $delivery->forceFill([
            'status' => 'pending',
            'attempts' => 0,
            'response_status' => null,
            'error' => null,
            'next_attempt_at' => null,
            'last_attempt_at' => null,
            'delivered_at' => null,
        ])->save();
        DeliverFlowEventWebhook::dispatch($delivery->getKey());

        if ($request->expectsJson()) {
            return response()->json(['queued' => true]);
        }

        return to_route('integrations.webhook-failures.index');
    }

    /** @return array<string, mixed> */
    private function serialize(FlowEventDelivery $delivery): array
    {
        return [
            'id' => $delivery->getKey(),
            'event' => $delivery->event?->name ?? 'Evento removido',
            'destination' => $delivery->webhook?->url ?? 'Destino removido',
            'responseStatus' => $delivery->response_status,
            'reason' => $delivery->error,
            'occurredAt' => ($delivery->last_attempt_at ?? $delivery->created_at)?->toIso8601String(),
            'attempts' => $delivery->attempts,
        ];
    }
}
