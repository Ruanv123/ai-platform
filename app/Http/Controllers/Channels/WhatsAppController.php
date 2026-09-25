<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\WhatsAppBusinessCategory;
use App\Enums\WhatsAppConnectionMethod;
use App\Exceptions\WhatsAppConnectionException;
use App\Exceptions\WhatsAppProfileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\ConnectWhatsAppWithMetaRequest;
use App\Http\Requests\Channels\SaveWhatsAppChannelRequest;
use App\Http\Requests\Channels\UpdateWhatsAppAgentRequest;
use App\Http\Requests\Channels\UpdateWhatsAppDisplayNameRequest;
use App\Http\Requests\Channels\UpdateWhatsAppProfilePictureRequest;
use App\Http\Requests\Channels\UpdateWhatsAppProfileTextsRequest;
use App\Http\Responses\WhatsAppProfileResponse;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Oficial\MetaErrorTranslator;
use App\WhatsApp\Oficial\MetaGraphApiResponseHandler;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use App\WhatsApp\TechProviderWhatsAppConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppProfileProvider $profileProvider) {}

    /**
     * Display the WhatsApp channel configuration page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Channel::class);

        $channel = $team->channels()
            ->where('type', ChannelType::WhatsApp)
            ->with(['agent:id,name', 'whatsappPhoneNumber.whatsappAccount'])
            ->first();
        $activeTab = $request->query('tab') === 'perfil' ? 'perfil' : 'conexao';

        return Inertia::render('channels/whatsapp', [
            'activeTab' => $activeTab,
            'whatsapp' => $channel === null ? null : $this->toFrontend(
                $channel,
                $user->can('update', $channel),
            ),
            'whatsappProfileCapabilities' => $this->profileProvider->capabilities()->toArray(),
            'whatsappProfileCategories' => WhatsAppBusinessCategory::options(),
            'whatsappProfile' => $activeTab === 'perfil'
                ? WhatsAppProfileResponse::deferredProfile($channel, $this->profileProvider)
                : null,
            'agents' => $team->agents()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ])
                ->values(),
            'permissions' => $user->toTeamPermissions($team),
            'webhookUrl' => route('webhooks.whatsapp.app-proprio'),
            'metaSignup' => [
                'appId' => filled(config('services.whatsapp.platform.app_id'))
                    ? (string) config('services.whatsapp.platform.app_id')
                    : null,
                'configId' => filled(config('services.whatsapp.platform.config_id'))
                    ? (string) config('services.whatsapp.platform.config_id')
                    : null,
                'graphApiVersion' => (string) config('services.whatsapp.graph_api_version'),
            ],
        ]);
    }

    public function connectWithMeta(
        ConnectWhatsAppWithMetaRequest $request,
        TechProviderWhatsAppConnector $connector,
    ): RedirectResponse {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        try {
            $connector->connect($team, $request->validated('code'));
        } catch (WhatsAppConnectionException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return to_route('channels.whatsapp.edit');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp conectado à Meta e Templates sincronizados.',
        ]);

        return to_route('channels.whatsapp.edit');
    }

    public function updateAgent(UpdateWhatsAppAgentRequest $request, Channel $channel): RedirectResponse
    {
        $channel->update(['agent_id' => $request->validated('agent_id')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente do WhatsApp atualizado com sucesso.',
        ]);

        return to_route('channels.whatsapp.edit');
    }

    /**
     * Store the WhatsApp channel, account and phone number atomically.
     */
    public function store(SaveWhatsAppChannelRequest $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        Gate::authorize('create', Channel::class);

        $validated = $request->validated();
        $agentId = $validated['agent_id'] ?? null;

        unset($validated['agent_id']);

        DB::transaction(function () use ($team, $validated, $agentId): void {
            $channel = $team->channels()->create([
                'type' => ChannelType::WhatsApp,
                'agent_id' => $agentId,
                'status' => ChannelStatus::NotConfigured,
                'last_error' => null,
            ]);

            $account = $team->whatsappAccounts()->create([
                'connection_method' => WhatsAppConnectionMethod::OwnApp,
                'waba_id' => $validated['waba_id'],
                'app_id' => $validated['app_id'] ?? null,
                'access_token' => $validated['access_token'],
                'app_secret' => $validated['app_secret'],
                'verify_token' => $this->generateVerifyToken(),
            ]);

            $channel->whatsappPhoneNumber()->create([
                'whatsapp_account_id' => $account->getKey(),
                'phone_number_id' => $validated['phone_number_id'],
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp configurado com sucesso.',
        ]);

        return to_route('channels.whatsapp.edit');
    }

    /**
     * Update the WhatsApp channel, account and phone number.
     */
    public function update(SaveWhatsAppChannelRequest $request, Channel $channel): RedirectResponse
    {
        Gate::authorize('update', $channel);

        $validated = $request->validated();
        $phoneNumber = $channel->whatsappPhoneNumber()->firstOrFail();
        $account = $phoneNumber->whatsappAccount()->firstOrFail();
        $accountAttributes = ['waba_id' => $validated['waba_id']];

        if (array_key_exists('app_id', $validated)) {
            $accountAttributes['app_id'] = $validated['app_id'];
        }

        foreach (['access_token', 'app_secret'] as $secret) {
            if (filled($validated[$secret] ?? null)) {
                $accountAttributes[$secret] = $validated[$secret];
            }
        }

        DB::transaction(function () use ($channel, $phoneNumber, $account, $validated, $accountAttributes): void {
            $channel->update([
                'agent_id' => $validated['agent_id'] ?? null,
            ]);
            $phoneNumber->update([
                'phone_number_id' => $validated['phone_number_id'],
            ]);
            $account->update($accountAttributes);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Configuração do WhatsApp atualizada com sucesso.',
        ]);

        return to_route('channels.whatsapp.edit');
    }

    /**
     * Test the saved credentials against the WhatsApp Graph API.
     */
    public function test(
        Channel $channel,
        MetaErrorTranslator $errorTranslator,
        MetaGraphApiResponseHandler $responseHandler,
    ): RedirectResponse {
        Gate::authorize('update', $channel);

        $phoneNumber = $channel->whatsappPhoneNumber()->firstOrFail();
        $account = $phoneNumber->whatsappAccount()->firstOrFail();
        $testedAt = now();

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($account->access_token)
                ->get(
                    rtrim((string) config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$phoneNumber->phone_number_id,
                    ['fields' => 'display_phone_number,verified_name,quality_rating,name_status,messaging_limit_tier'],
                );
            $responseHandler->markChannelUnavailableForInvalidToken($response, $channel);

            if ($response->successful()) {
                DB::transaction(function () use ($channel, $phoneNumber, $account, $response, $testedAt): void {
                    $channel->update([
                        'status' => ChannelStatus::Connected,
                        'last_error' => null,
                    ]);
                    $phoneNumber->update([
                        'display_phone_number' => $response->json('display_phone_number'),
                        'verified_name' => $response->json('verified_name'),
                        'quality_rating' => $response->json('quality_rating'),
                        'name_status' => $response->json('name_status'),
                        'messaging_limit_tier' => $response->json('messaging_limit_tier'),
                    ]);
                    $account->update(['last_tested_at' => $testedAt]);
                });

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => 'Conexão testada com sucesso.',
                ]);

                return to_route('channels.whatsapp.edit');
            }

            $errorMessage = $errorTranslator->translate($response->json('error'));
        } catch (ConnectionException) {
            $errorMessage = 'Não foi possível conectar à Meta. Verifique sua conexão e tente novamente.';
        }

        DB::transaction(function () use ($channel, $phoneNumber, $account, $errorMessage, $testedAt): void {
            $channel->update([
                'status' => ChannelStatus::Error,
                'last_error' => $errorMessage,
            ]);
            $phoneNumber->update([
                'display_phone_number' => null,
                'verified_name' => null,
                'quality_rating' => null,
                'name_status' => null,
                'messaging_limit_tier' => null,
            ]);
            $account->update(['last_tested_at' => $testedAt]);
        });

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $errorMessage,
        ]);

        return to_route('channels.whatsapp.edit');
    }

    public function updateProfileTexts(
        UpdateWhatsAppProfileTextsRequest $request,
        Channel $channel,
    ): RedirectResponse {
        try {
            $this->profileProvider->updateTexts($channel, $request->validated());
        } catch (WhatsAppProfileException $exception) {
            return WhatsAppProfileResponse::updateFailed('channels.whatsapp.edit', $exception->getMessage());
        }

        return WhatsAppProfileResponse::updateSucceeded(
            'channels.whatsapp.edit',
            'Perfil do WhatsApp atualizado com sucesso.',
        );
    }

    public function updateProfilePicture(
        UpdateWhatsAppProfilePictureRequest $request,
        Channel $channel,
    ): RedirectResponse {
        try {
            $this->profileProvider->updateProfilePicture($channel, $request->photo());
        } catch (WhatsAppProfileException $exception) {
            return WhatsAppProfileResponse::updateFailed('channels.whatsapp.edit', $exception->getMessage());
        }

        return WhatsAppProfileResponse::updateSucceeded(
            'channels.whatsapp.edit',
            'Foto do Perfil do WhatsApp atualizada com sucesso.',
        );
    }

    public function requestDisplayName(
        UpdateWhatsAppDisplayNameRequest $request,
        Channel $channel,
    ): RedirectResponse {
        try {
            $this->profileProvider->requestDisplayName($channel, $request->validated('name'));
        } catch (WhatsAppProfileException $exception) {
            return WhatsAppProfileResponse::updateFailed('channels.whatsapp.edit', $exception->getMessage());
        }

        return WhatsAppProfileResponse::updateSucceeded(
            'channels.whatsapp.edit',
            'Pedido de alteração do nome enviado para análise da Meta.',
        );
    }

    /**
     * Remove the WhatsApp channel and its specific configuration.
     */
    public function destroy(Channel $channel): RedirectResponse
    {
        Gate::authorize('delete', $channel);

        DB::transaction(function () use ($channel): void {
            $account = $channel->whatsappPhoneNumber?->whatsappAccount;
            $channel->delete();

            if ($account !== null && $account->phoneNumbers()->doesntExist()) {
                $account->delete();
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Número do WhatsApp removido com sucesso.',
        ]);

        return to_route('channels.whatsapp.edit');
    }

    /**
     * Serialize only safe channel data for the frontend.
     *
     * @return array<string, mixed>
     */
    private function toFrontend(Channel $channel, bool $canManage): array
    {
        $phoneNumber = $channel->whatsappPhoneNumber;
        $account = $phoneNumber?->whatsappAccount;

        return [
            'id' => $channel->id,
            'wabaId' => $account?->waba_id,
            'appId' => $account?->app_id,
            'phoneNumberId' => $phoneNumber?->phone_number_id,
            'accessTokenSet' => $canManage && filled($account?->access_token),
            'accessTokenMasked' => $canManage ? $this->maskSecret($account?->access_token) : null,
            'appSecretSet' => $canManage && filled($account?->app_secret),
            'appSecretMasked' => $canManage ? $this->maskSecret($account?->app_secret) : null,
            'verifyToken' => $canManage ? $account?->verify_token : null,
            'method' => $account?->connection_method?->value,
            'status' => $channel->status->value,
            'statusLabel' => $channel->status->label(),
            'lastError' => $channel->last_error,
            'displayPhoneNumber' => $phoneNumber?->display_phone_number,
            'verifiedName' => $phoneNumber?->verified_name,
            'qualityRating' => $phoneNumber?->quality_rating,
            'nameStatus' => $phoneNumber?->name_status,
            'messagingLimitTier' => $phoneNumber?->messaging_limit_tier,
            'credentialsSavedAt' => $account?->created_at?->toIso8601String(),
            'lastTestedAt' => $account?->last_tested_at?->toIso8601String(),
            'webhookVerifiedAt' => $account?->webhook_verified_at?->toIso8601String(),
            'subscribedAt' => $account?->subscribed_at?->toIso8601String(),
            'registeredAt' => $phoneNumber?->registered_at?->toIso8601String(),
            'nameStatus' => $phoneNumber?->name_status,
            'messagingLimitTier' => $phoneNumber?->messaging_limit_tier,
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
     * Generate a verify token that is unique across every WhatsApp account.
     */
    private function generateVerifyToken(): string
    {
        do {
            $verifyToken = Str::random(64);
        } while (WhatsAppAccount::query()->where('verify_token', $verifyToken)->exists());

        return $verifyToken;
    }
}
