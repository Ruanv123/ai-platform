<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\WhatsAppConnectionMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\SaveWhatsAppChannelRequest;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\WhatsAppAccount;
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
    /**
     * Display the WhatsApp channel configuration page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Channel::class);

        $channel = $team->channels()
            ->where('type', ChannelType::WhatsApp)
            ->with(['agent:id,name', 'whatsappPhoneNumber.whatsappAccount'])
            ->first();

        return Inertia::render('channels/whatsapp', [
            'whatsapp' => $channel === null ? null : $this->toFrontend(
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
            'webhookUrl' => url('/webhooks/whatsapp'),
        ]);
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

            $phoneNumber = $channel->whatsappPhoneNumber()->create([
                'phone_number_id' => $validated['phone_number_id'],
            ]);

            $phoneNumber->whatsappAccount()->create([
                'method' => WhatsAppConnectionMethod::Manual,
                'waba_id' => $validated['waba_id'],
                'access_token' => $validated['access_token'],
                'app_secret' => $validated['app_secret'],
                'verify_token' => $this->generateVerifyToken(),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp configurado com sucesso.',
        ]);

        return to_route('channels.index');
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

        return to_route('channels.index');
    }

    /**
     * Test the saved credentials against the WhatsApp Graph API.
     */
    public function test(Channel $channel): RedirectResponse
    {
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
                    ['fields' => 'display_phone_number,verified_name,quality_rating'],
                );

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
                    ]);
                    $account->update(['last_tested_at' => $testedAt]);
                });

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => 'Conexão testada com sucesso.',
                ]);

                return to_route('channels.index');
            }

            $errorMessage = $this->translateMetaError($response->json('error'));
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
            ]);
            $account->update(['last_tested_at' => $testedAt]);
        });

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $errorMessage,
        ]);

        return to_route('channels.index');
    }

    /**
     * Remove the WhatsApp channel and its specific configuration.
     */
    public function destroy(Channel $channel): RedirectResponse
    {
        Gate::authorize('delete', $channel);
        $channel->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Número do WhatsApp removido com sucesso.',
        ]);

        return to_route('channels.index');
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
            'phoneNumberId' => $phoneNumber?->phone_number_id,
            'accessTokenSet' => $canManage && filled($account?->access_token),
            'accessTokenMasked' => $canManage ? $this->maskSecret($account?->access_token) : null,
            'appSecretSet' => $canManage && filled($account?->app_secret),
            'appSecretMasked' => $canManage ? $this->maskSecret($account?->app_secret) : null,
            'verifyToken' => $canManage ? $account?->verify_token : null,
            'method' => $account?->method?->value,
            'status' => $channel->status->value,
            'statusLabel' => $channel->status->label(),
            'lastError' => $channel->last_error,
            'displayPhoneNumber' => $phoneNumber?->display_phone_number,
            'verifiedName' => $phoneNumber?->verified_name,
            'qualityRating' => $phoneNumber?->quality_rating,
            'lastTestedAt' => $account?->last_tested_at?->toIso8601String(),
            'webhookVerifiedAt' => $account?->webhook_verified_at?->toIso8601String(),
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

    /**
     * Translate common Graph API failures into actionable Portuguese messages.
     *
     * @param  array<string, mixed>|null  $error
     */
    private function translateMetaError(?array $error): string
    {
        $code = (int) ($error['code'] ?? 0);
        $type = (string) ($error['type'] ?? '');

        return match (true) {
            $code === 190 => 'O access token é inválido ou expirou.',
            $code === 10 => 'O token não tem permissão para acessar este número.',
            $code === 100 => 'O Phone Number ID ou os campos informados são inválidos.',
            $type === 'OAuthException' => 'O access token é inválido ou expirou.',
            default => 'A Meta recusou a conexão. Verifique as credenciais e as permissões do aplicativo.',
        };
    }
}
