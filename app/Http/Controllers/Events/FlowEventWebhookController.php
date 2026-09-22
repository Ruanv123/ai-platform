<?php

namespace App\Http\Controllers\Events;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\SaveFlowEventWebhookRequest;
use App\Models\FlowEvent;
use App\Models\FlowEventWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FlowEventWebhookController extends Controller
{
    public function store(SaveFlowEventWebhookRequest $request, FlowEvent $flowEvent): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $flowEvent);
        $webhook = $flowEvent->webhooks()->create([
            'team_id' => $flowEvent->team_id,
            'url' => $request->validated('url'),
            'secret' => Str::random(64),
            'active' => $request->boolean('active', true),
        ]);

        $response = [
            'webhook' => [
                'id' => $webhook->getKey(),
                'url' => $webhook->url,
                'active' => $webhook->active,
            ],
            'secret' => $webhook->secret,
            'message' => 'Guarde este segredo. Ele será mostrado apenas agora.',
        ];
        if ($request->expectsJson()) {
            return response()->json($response, 201);
        }

        return to_route('integrations.index');
    }

    public function destroy(Request $request, FlowEvent $flowEvent, FlowEventWebhook $webhook): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $flowEvent);
        abort_unless($webhook->team_id === $flowEvent->team_id && $webhook->flow_event_id === $flowEvent->getKey(), 404);
        $webhook->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return to_route('integrations.index');
    }

    private function authorizeManagement(Request $request, FlowEvent $flowEvent): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        abort_unless($flowEvent->team_id === $team->getKey(), 404);
    }
}
