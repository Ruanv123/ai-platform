<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\SaveWhatsAppTestChannelRequest;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTestController extends Controller
{
    /**
     * Display the WhatsApp test channel configuration page.
     */
    public function edit(Request $request): Response
    {
        $this->ensureVisible($request->user());

        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Channel::class);

        $channel = $team->channels()
            ->where('type', ChannelType::WhatsAppTest)
            ->with(['agent:id,name', 'whatsappTestConnection'])
            ->first();

        return Inertia::render('channels/whatsapp-test', [
            'whatsappTest' => $channel === null ? null : $this->toFrontend(
                $channel,
                $user->can('update', $channel),
            ),
            'agents' => $team->agents()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ])
                ->values(),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Store the test channel and its connection atomically.
     */
    public function store(SaveWhatsAppTestChannelRequest $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        Gate::authorize('create', Channel::class);

        $validated = $request->validated();
        $agentId = $validated['agent_id'] ?? null;
        unset($validated['agent_id']);

        DB::transaction(function () use ($team, $validated, $agentId): void {
            $channel = $team->channels()->create([
                'type' => ChannelType::WhatsAppTest,
                'agent_id' => $agentId,
                'status' => ChannelStatus::Connected,
                'last_error' => null,
            ]);

            $channel->whatsappTestConnection()->create($validated);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp de testes configurado com sucesso.',
        ]);

        return to_route('channels.whatsapp-test.edit');
    }

    /**
     * Update the test channel and its connection atomically.
     */
    public function update(
        SaveWhatsAppTestChannelRequest $request,
        Channel $whatsappTestChannel,
    ): RedirectResponse {
        Gate::authorize('update', $whatsappTestChannel);

        $validated = $request->validated();
        $connection = $whatsappTestChannel->whatsappTestConnection()->firstOrFail();
        $connectionAttributes = [
            'service_url' => $validated['service_url'],
            'instance_name' => $validated['instance_name'],
        ];

        if (filled($validated['access_token'] ?? null)) {
            $connectionAttributes['access_token'] = $validated['access_token'];
        }

        DB::transaction(function () use ($whatsappTestChannel, $connection, $connectionAttributes, $validated): void {
            $whatsappTestChannel->update([
                'agent_id' => $validated['agent_id'] ?? null,
                'status' => ChannelStatus::Connected,
                'last_error' => null,
            ]);
            $connection->update($connectionAttributes);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Configuração do WhatsApp de testes atualizada com sucesso.',
        ]);

        return to_route('channels.whatsapp-test.edit');
    }

    /**
     * Remove the test channel and its specific connection.
     */
    public function destroy(Request $request, Channel $whatsappTestChannel): RedirectResponse
    {
        $this->ensureVisible($request->user());
        Gate::authorize('delete', $whatsappTestChannel);
        $whatsappTestChannel->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp de testes removido com sucesso.',
        ]);

        return to_route('channels.whatsapp-test.edit');
    }

    /**
     * Serialize only safe connection data for the frontend.
     *
     * @return array<string, mixed>
     */
    private function toFrontend(Channel $channel, bool $canManage): array
    {
        $connection = $channel->whatsappTestConnection;

        return [
            'id' => $channel->id,
            'serviceUrl' => $connection?->service_url,
            'instanceName' => $connection?->instance_name,
            'accessTokenSet' => $canManage && filled($connection?->access_token),
            'accessTokenMasked' => $canManage ? $this->maskSecret($connection?->access_token) : null,
            'status' => $channel->status->value,
            'statusLabel' => $channel->status->label(),
            'lastError' => $channel->last_error,
            'credentialsSavedAt' => $connection?->created_at?->toIso8601String(),
            'agent' => $channel->agent === null ? null : [
                'id' => $channel->agent->id,
                'name' => $channel->agent->name,
            ],
        ];
    }

    /**
     * Mask a secret without exposing any of its value to the client.
     */
    private function maskSecret(?string $secret): ?string
    {
        return filled($secret) ? '••••••••' : null;
    }

    /**
     * Restrict this development-only channel to local installations and platform administrators.
     */
    private function ensureVisible(?User $user): void
    {
        abort_unless(ChannelType::WhatsAppTest->isVisible($user), 404);
    }
}
