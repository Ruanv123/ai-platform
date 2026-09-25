<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Exceptions\WhatsAppProfileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\SaveWhatsAppTestChannelRequest;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use App\WhatsApp\Testes\EvolutionGoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WhatsAppTestController extends Controller
{
    public function __construct(
        private readonly EvolutionGoClient $evolutionGo,
        private readonly WhatsAppProfileProvider $profileProvider,
    ) {}

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

        $channel = $team
            ->channels()
            ->where('type', ChannelType::WhatsAppTest)
            ->with(['agent:id,name', 'whatsappTestConnection'])
            ->first();
        $canManage = $channel !== null && $user->can('update', $channel);
        $activeTab = $request->query('tab') === 'perfil' ? 'perfil' : 'conexao';

        return Inertia::render('channels/whatsapp-test', [
            'activeTab' => $activeTab,
            'whatsappTest' => $channel === null
                    ? null
                    : $this->toFrontend($channel, $canManage, $activeTab === 'conexao'),
            'whatsappProfileCapabilities' => $this->profileProvider->capabilities()->toArray(),
            'whatsappProfile' => $activeTab === 'perfil'
                ? Inertia::defer(function () use ($channel): array {
                    if ($channel === null || $channel->status !== ChannelStatus::Connected) {
                        return ['state' => 'not_connected'];
                    }

                    try {
                        return [
                            'state' => 'ready',
                            'data' => $this->profileProvider->read($channel)->toArray(),
                        ];
                    } catch (WhatsAppProfileException $exception) {
                        Inertia::flash('toast', [
                            'type' => 'error',
                            'message' => $exception->getMessage(),
                        ]);

                        return [
                            'state' => 'error',
                            'message' => $exception->getMessage(),
                        ];
                    }
                })
                : null,
            'agents' => $team
                ->agents()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    fn (Agent $agent): array => [
                        'id' => $agent->id,
                        'name' => $agent->name,
                    ],
                )
                ->values(),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Create the instance on the platform's Evolution Go server and store the Channel.
     */
    public function store(
        SaveWhatsAppTestChannelRequest $request,
    ): RedirectResponse {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        Gate::authorize('create', Channel::class);

        $validated = $request->validated();
        $name = $validated['name'];
        $instanceName = 'canal_'.$team->getKey();

        try {
            $instanceToken = $this->evolutionGo->createInstance($instanceName);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'name' => $exception->getMessage(),
            ]);
        }

        $channel = DB::transaction(function () use (
            $team,
            $validated,
            $name,
            $instanceName,
            $instanceToken,
        ): Channel {
            $channel = $team->channels()->create([
                'type' => ChannelType::WhatsAppTest,
                'agent_id' => $validated['agent_id'] ?? null,
                'status' => ChannelStatus::NotConfigured,
                'last_error' => null,
            ]);

            $channel->whatsappTestConnection()->create([
                'service_url' => config('services.evolution.base_url'),
                'instance_name' => $instanceName,
                'name' => $name,
                'access_token' => $instanceToken,
                'qrcode' => null,
            ]);

            return $channel;
        });

        Log::info('[WHATSAPP-TEST-QR] connection.persisted', [
            'channel_id' => $channel->getKey(),
            'instance_name' => $instanceName,
        ]);

        try {
            $this->evolutionGo->connect(
                $instanceToken,
                config('services.evolution.webhook_url') ?: route('webhooks.whatsapp-test'),
            );
        } catch (RuntimeException $exception) {
            Log::warning('[WHATSAPP-TEST-QR] evolution.connect_failed', [
                'channel_id' => $channel->getKey(),
                'exception_type' => $exception::class,
            ]);

            $channel->delete();

            throw ValidationException::withMessages([
                'name' => $exception->getMessage(),
            ]);
        }

        Log::info('[WHATSAPP-TEST-QR] evolution.connect_completed', [
            'channel_id' => $channel->getKey(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Integração criada. Escaneie o QR code para conectar.',
        ]);

        return to_route('channels.whatsapp-test.edit');
    }

    /**
     * Update the Agent linked to the test channel.
     */
    public function update(
        SaveWhatsAppTestChannelRequest $request,
        Channel $whatsappTestChannel,
    ): RedirectResponse {
        Gate::authorize('update', $whatsappTestChannel);

        $validated = $request->validated();
        $whatsappTestChannel->update([
            'agent_id' => $validated['agent_id'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Configuração do WhatsApp de testes atualizada com sucesso.',
        ]);

        return to_route('channels.whatsapp-test.edit');
    }

    /**
     * Remove the test channel and its specific connection.
     */
    public function destroy(
        Request $request,
        Channel $whatsappTestChannel,
    ): RedirectResponse {
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
    private function toFrontend(Channel $channel, bool $canManage, bool $includeQrCode): array
    {
        $connection = $channel->whatsappTestConnection;

        return [
            'id' => $channel->id,
            'name' => $connection?->name,
            'qrcode' => $canManage && $includeQrCode ? $this->liveQrCode($channel) : null,
            'status' => $channel->status->value,
            'statusLabel' => $channel->status->label(),
            'lastError' => $channel->last_error,
            'credentialsSavedAt' => $connection?->created_at?->toIso8601String(),
            'agent' => $channel->agent === null
                    ? null
                    : [
                        'id' => $channel->agent->id,
                        'name' => $channel->agent->name,
                    ],
        ];
    }

    /**
     * Read the current QR code straight from Evolution Go, since it rotates faster than we could store it.
     */
    private function liveQrCode(Channel $channel): ?string
    {
        $connection = $channel->whatsappTestConnection;

        if ($connection === null || $channel->status === ChannelStatus::Connected) {
            return null;
        }

        try {
            if ($this->evolutionGo->isLoggedIn($connection->access_token)) {
                $channel->update(['status' => ChannelStatus::Connected, 'last_error' => null]);

                return null;
            }

            return $this->evolutionGo->qrcode($connection->access_token);
        } catch (RuntimeException $exception) {
            Log::warning('[WHATSAPP-TEST-QR] evolution.qrcode_failed', [
                'channel_id' => $channel->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Restrict this development-only channel to local installations and platform administrators.
     */
    private function ensureVisible(?User $user): void
    {
        abort_unless(ChannelType::WhatsAppTest->isVisible($user), 404);
    }
}
