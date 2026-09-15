<?php

namespace App\Http\Controllers\Channels;

use App\Enums\WhatsAppConnectionMethod;
use App\Enums\WhatsAppConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\SaveWhatsAppNumberRequest;
use App\Models\Agent;
use App\Models\WhatsAppNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    /**
     * Display the channel configuration page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', WhatsAppNumber::class);

        $whatsappNumber = $team->whatsappNumbers()->with('agent:id,name')->first();

        return Inertia::render('channels/whatsapp', [
            'whatsapp' => $whatsappNumber === null ? null : $this->toFrontend(
                $whatsappNumber,
                $user->can('update', $whatsappNumber),
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
     * Store the WhatsApp channel configuration.
     */
    public function store(SaveWhatsAppNumberRequest $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        Gate::authorize('create', WhatsAppNumber::class);

        $validated = $request->validated();
        $validated['method'] = WhatsAppConnectionMethod::Manual;
        $validated['verify_token'] = $this->generateVerifyToken();
        $validated['status'] = WhatsAppConnectionStatus::Unverified;

        $team->whatsappNumbers()->create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp configurado com sucesso.',
        ]);

        return to_route('channels.index');
    }

    /**
     * Update the WhatsApp channel configuration.
     */
    public function update(SaveWhatsAppNumberRequest $request, WhatsAppNumber $whatsappNumber): RedirectResponse
    {
        Gate::authorize('update', $whatsappNumber);

        $validated = $request->validated();

        foreach (['access_token', 'app_secret'] as $secret) {
            if (blank($validated[$secret] ?? null)) {
                unset($validated[$secret]);
            }
        }

        $whatsappNumber->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Configuração do WhatsApp atualizada com sucesso.',
        ]);

        return to_route('channels.index');
    }

    /**
     * Test the saved credentials against the WhatsApp Graph API.
     */
    public function test(WhatsAppNumber $whatsappNumber): RedirectResponse
    {
        Gate::authorize('update', $whatsappNumber);

        $testedAt = now();

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($whatsappNumber->access_token)
                ->get(
                    rtrim((string) config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$whatsappNumber->phone_number_id,
                    ['fields' => 'display_phone_number,verified_name,quality_rating'],
                );

            if ($response->successful()) {
                $whatsappNumber->update([
                    'status' => WhatsAppConnectionStatus::Connected,
                    'last_error' => null,
                    'display_phone_number' => $response->json('display_phone_number'),
                    'verified_name' => $response->json('verified_name'),
                    'quality_rating' => $response->json('quality_rating'),
                    'last_tested_at' => $testedAt,
                ]);

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

        $whatsappNumber->update([
            'status' => WhatsAppConnectionStatus::Error,
            'last_error' => $errorMessage,
            'display_phone_number' => null,
            'verified_name' => null,
            'quality_rating' => null,
            'last_tested_at' => $testedAt,
        ]);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $errorMessage,
        ]);

        return to_route('channels.index');
    }

    /**
     * Remove the WhatsApp channel configuration.
     */
    public function destroy(WhatsAppNumber $whatsappNumber): RedirectResponse
    {
        Gate::authorize('delete', $whatsappNumber);
        $whatsappNumber->delete();

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
    private function toFrontend(WhatsAppNumber $whatsappNumber, bool $canManage): array
    {
        return [
            'id' => $whatsappNumber->id,
            'wabaId' => $whatsappNumber->waba_id,
            'phoneNumberId' => $whatsappNumber->phone_number_id,
            'accessTokenSet' => $canManage && filled($whatsappNumber->access_token),
            'accessTokenMasked' => $canManage ? $this->maskSecret($whatsappNumber->access_token) : null,
            'appSecretSet' => $canManage && filled($whatsappNumber->app_secret),
            'appSecretMasked' => $canManage ? $this->maskSecret($whatsappNumber->app_secret) : null,
            'verifyToken' => $canManage ? $whatsappNumber->verify_token : null,
            'method' => $whatsappNumber->method->value,
            'status' => $whatsappNumber->status->value,
            'statusLabel' => $whatsappNumber->status->label(),
            'lastError' => $whatsappNumber->last_error,
            'displayPhoneNumber' => $whatsappNumber->display_phone_number,
            'verifiedName' => $whatsappNumber->verified_name,
            'qualityRating' => $whatsappNumber->quality_rating,
            'lastTestedAt' => $whatsappNumber->last_tested_at?->toIso8601String(),
            'webhookVerifiedAt' => $whatsappNumber->webhook_verified_at?->toIso8601String(),
            'agent' => $whatsappNumber->agent === null ? null : [
                'id' => $whatsappNumber->agent->id,
                'name' => $whatsappNumber->agent->name,
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
     * Generate a verify token that is unique across every Empresa.
     */
    private function generateVerifyToken(): string
    {
        do {
            $verifyToken = Str::random(64);
        } while (WhatsAppNumber::withoutGlobalScopes()->where('verify_token', $verifyToken)->exists());

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
