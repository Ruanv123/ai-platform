<?php

namespace App\Http\Controllers\Integrations;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyAccessRevokedException;
use App\Ecommerce\ShopifyInstallationCleanup;
use App\Enums\CrmProvider;
use App\Enums\EcommerceProvider;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\SaveIntegrationRequest;
use App\Integrations\GoogleCalendarClient;
use App\Integrations\IntegrationConnectionTester;
use App\Models\Flow;
use App\Models\Integration;
use App\Models\Product;
use App\Models\SchedulableCalendar;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class IntegrationController extends Controller
{
    /** @var list<string> */
    private const SINGLETON_TYPES = ['crm', 'ecommerce'];

    private const CRM_METADATA_REFRESH_SECONDS = 300;

    /** @var list<string> */
    private const GOOGLE_SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.freebusy',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
    ];

    public function index(Request $request): Response
    {
        $team = $request->user()->company();
        $team->load('integrations');
        $configured = $team->integrations->keyBy('type');

        return Inertia::render('integrations/index', [
            'crmCatalog' => $this->crmCatalog(),
            'ecommerceCatalog' => $this->ecommerceCatalog(),
            'agendaCatalog' => [$this->agendaCatalogEntry()],
            'singletonIntegrations' => [
                'crm' => optional($configured->get('crm'), fn (Integration $integration): array => $this->serialize($integration)),
                'ecommerce' => optional($configured->get('ecommerce'), fn (Integration $integration): array => $this->serialize($integration)),
                'agenda' => optional($configured->get('agenda'), fn (Integration $integration): array => $this->serialize($integration)),
            ],
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function show(Request $request, string $type, CrmProviderAdapter $crmAdapter): Response
    {
        $team = $request->user()->company();
        $integration = $team->integrations()->where('type', $type)->first();
        $agendaCalendars = [];
        $googleCalendars = [];
        $agendaError = session('agenda_error');
        $shop = null;
        $crmError = session('crm_error');

        if ($type === 'crm'
            && $integration?->status === 'connected'
            && ($integration->crm_metadata_loaded_at === null
                || $integration->crm_metadata_loaded_at->lte(now()->subSeconds(self::CRM_METADATA_REFRESH_SECONDS)))) {
            try {
                $integration->forceFill([
                    'crm_metadata' => $crmAdapter->loadMetadata($integration),
                    'crm_metadata_loaded_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                report($exception);
                $integration->refresh();
                $crmError ??= $exception->getMessage();
            }
        }

        if ($type === 'agenda' && $integration !== null) {
            if ($integration->status === 'connected') {
                try {
                    $googleCalendars = (new GoogleCalendarClient($integration))->calendars();
                    $googleCalendarIds = collect($googleCalendars)->pluck('id')->filter()->all();
                    $integration->schedulableCalendars()
                        ->whereNotIn('google_calendar_id', $googleCalendarIds)
                        ->delete();
                } catch (Throwable $exception) {
                    $agendaError ??= $exception->getMessage();
                }
            }
            $agendaCalendars = $integration->schedulableCalendars()
                ->orderBy('display_name')
                ->get()
                ->map(fn (SchedulableCalendar $calendar): array => $this->serializeSchedulableCalendar($calendar))
                ->values()
                ->all();
        }

        if ($type === 'ecommerce' && ($request->query('provider') === 'shopify' || $integration?->provider === 'shopify')) {
            $shop = $team->shops()->where('integration_id', $integration?->getKey())->first();
        }

        return Inertia::render('integrations/show', [
            'type' => $type,
            'catalog' => match ($type) {
                'crm' => $this->crmCatalog(),
                'ecommerce' => $this->ecommerceCatalog(),
                default => [$this->agendaCatalogEntry()],
            },
            'provider' => $request->query('provider'),
            'integration' => $integration === null ? null : $this->serialize($integration),
            'googleCalendars' => $googleCalendars,
            'schedulableCalendars' => $agendaCalendars,
            'agendaError' => $agendaError,
            'shop' => $shop === null ? null : $this->serializeShop($shop),
            'shopifyError' => session('shopify_error'),
            'shopifySuccess' => session('shopify_success'),
            'crmError' => $crmError,
            'crmSuccess' => session('crm_success'),
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        return Socialite::driver('google')
            ->setScopes(self::GOOGLE_SCOPES)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $state = $request->input('state');
        $sessionState = $request->session()->get('state');
        if ($request->has('state') && (! is_string($state) || ! is_string($sessionState) || ! hash_equals($sessionState, $state))) {
            return to_route('integrations.show', 'agenda')->with('agenda_error', 'A autorização da Agenda expirou. Tente conectar novamente.');
        }
        if ($request->filled('error')) {
            return to_route('integrations.show', 'agenda')->with('agenda_error', 'A autorização da Agenda foi recusada no Google.');
        }

        try {
            $googleUser = Socialite::driver('google')
                ->setScopes(self::GOOGLE_SCOPES)
                ->user();
            $email = $googleUser->getEmail();
            if (! is_string($email) || $email === '' || ! is_string($googleUser->token ?? null)) {
                throw new RuntimeException('O Google não retornou uma conta válida.');
            }

            $team = $request->user()->company();
            $existing = $team->integrations()->where('type', 'agenda')->first();
            $integration = $existing ?? $team->integrations()->make(['type' => 'agenda']);
            $integration->fill([
                'name' => 'Google Agenda',
                'type' => 'agenda',
                'provider' => 'google',
                'credentials' => [
                    'access_token' => $googleUser->token,
                    'refresh_token' => is_string($googleUser->refreshToken ?? null) ? $googleUser->refreshToken : ($existing?->credential('refresh_token')),
                    'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600))->timestamp,
                    'email' => $email,
                ],
                'status' => 'connected',
                'status_message' => null,
                'last_tested_at' => now(),
            ]);
            $integration->save();

            return to_route('integrations.show', 'agenda')->with('agenda_success', 'Agenda conectada como '.$email.'.');
        } catch (Throwable $exception) {
            report($exception);

            return to_route('integrations.show', 'agenda')->with('agenda_error', 'Não foi possível conectar a Agenda. '.$exception->getMessage());
        }
    }

    public function store(SaveIntegrationRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request);
        $team = $request->user()->company();
        $data = $request->validated();
        $this->rejectCrmCredentialWorkflow($data);
        $this->rejectShopifyCredentialWorkflow($data);
        $existing = in_array($data['type'], self::SINGLETON_TYPES, true)
            ? $team->integrations()->where('type', $data['type'])->first()
            : null;

        if ($existing !== null) {
            $existing->update([
                'name' => $data['name'],
                'provider' => $data['provider'] ?? null,
                'credentials' => $this->credentials($data, $existing->credentials),
                'status' => 'never_tested',
                'status_message' => null,
                'last_tested_at' => null,
            ]);
            $integration = $existing->fresh();
        } else {
            $integration = $team->integrations()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'provider' => $data['provider'] ?? null,
                'credentials' => $this->credentials($data),
                'status' => 'never_tested',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($this->serialize($integration), $existing === null ? 201 : 200);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integração cadastrada. Teste a conexão para ativá-la.']);

        return $this->redirectToIntegration($integration);
    }

    public function update(SaveIntegrationRequest $request, Integration $integration): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $integration);
        $data = $request->validated();
        $this->rejectCrmCredentialWorkflow($data, $integration);
        $this->rejectShopifyCredentialWorkflow($data, $integration);
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

        return $this->redirectToIntegration($integration);
    }

    public function test(Request $request, Integration $integration, IntegrationConnectionTester $tester): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $integration);
        $this->rejectCrmCredentialWorkflow([], $integration);
        $this->rejectShopifyCredentialWorkflow([], $integration);
        abort_if($integration->type === 'agenda', 404);
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

        Inertia::flash('toast', ['type' => $result['connected'] ? 'success' : 'error', 'message' => $result['message']]);

        return $this->redirectToIntegration($integration);
    }

    public function destroy(
        Request $request,
        Integration $integration,
        EcommerceAdapter $ecommerceAdapter,
        ShopifyInstallationCleanup $shopifyInstallationCleanup,
    ): RedirectResponse|JsonResponse {
        $this->authorizeManagement($request, $integration);
        $isShopify = $integration->type === 'ecommerce' && $integration->provider === 'shopify';
        if ($integration->type !== 'agenda' && ! $isShopify) {
            $usingFlows = $this->flowsUsing($request->user()->company(), $integration->getKey());
            if ($usingFlows->isNotEmpty()) {
                $message = 'Não é possível apagar esta Integração porque ela é usada por: '.$usingFlows->join(', ').'. Remova-a dos Fluxos antes de continuar.';
                throw ValidationException::withMessages(['integration' => $message]);
            }
        }

        if ($integration->type === 'agenda') {
            try {
                $token = $integration->credential('access_token');
                if (is_string($token) && $token !== '') {
                    Http::connectTimeout(3)->timeout(5)->asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
                }
            } catch (Throwable $exception) {
                Log::warning('Google Agenda token revocation failed', ['integration_id' => $integration->getKey(), 'exception' => $exception]);
            }
        }

        if ($isShopify) {
            $shop = $integration->shop()->first();

            if ($shop !== null) {
                try {
                    $ecommerceAdapter->uninstallApp($shop);
                } catch (ShopifyAccessRevokedException) {
                } catch (Throwable $exception) {
                    report($exception);
                    throw ValidationException::withMessages([
                        'integration' => $exception instanceof RuntimeException
                            ? $exception->getMessage()
                            : 'Não foi possível desinstalar o app na Shopify. Tente novamente.',
                    ]);
                }

                $shopifyInstallationCleanup->removeAfterUninstall($shop);
            } else {
                DB::transaction(function () use ($integration): void {
                    $integration->forceFill([
                        'credentials' => [],
                        'status' => 'disconnected',
                        'status_message' => null,
                    ])->save();
                    $integration->delete();
                });
            }
        } else {
            $integration->forceDelete();
        }

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integração apagada.']);

        return to_route('integrations.index');
    }

    private function crmCatalog(): array
    {
        return array_map(fn (array $item): array => [
            ...$item,
            'planned' => $item['value'] !== CrmProvider::RdStation->value,
            'available' => $item['value'] === CrmProvider::RdStation->value,
        ], CrmProvider::catalog());
    }

    private function rejectCrmCredentialWorkflow(array $data, ?Integration $integration = null): void
    {
        $isCrmIntegration = $integration?->type === 'crm';
        $isCrmRequest = ($data['type'] ?? null) === 'crm';

        if ($isCrmIntegration || $isCrmRequest) {
            throw ValidationException::withMessages([
                'provider' => 'O RD Station CRM deve ser conectado pela autorização oficial.',
            ]);
        }
    }

    private function ecommerceCatalog(): array
    {
        return array_map(fn (array $item): array => [
            ...$item,
            'planned' => $item['value'] !== EcommerceProvider::Shopify->value,
            'available' => $item['value'] === EcommerceProvider::Shopify->value,
        ], EcommerceProvider::catalog());
    }

    private function rejectShopifyCredentialWorkflow(array $data, ?Integration $integration = null): void
    {
        $isShopifyIntegration = $integration?->type === 'ecommerce' && $integration->provider === EcommerceProvider::Shopify->value;
        $isShopifyRequest = ($data['type'] ?? null) === 'ecommerce' && ($data['provider'] ?? null) === EcommerceProvider::Shopify->value;

        if ($isShopifyIntegration || $isShopifyRequest) {
            throw ValidationException::withMessages([
                'provider' => 'A Shopify deve ser conectada e gerenciada pelo fluxo de autorização próprio.',
            ]);
        }
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

    private function redirectToIntegration(Integration $integration): RedirectResponse
    {
        if ($integration->type === 'agenda' || in_array($integration->type, self::SINGLETON_TYPES, true)) {
            return to_route('integrations.show', $integration->type);
        }

        return to_route('integrations.index');
    }

    /** @return array<string, mixed> */
    private function agendaCatalogEntry(): array
    {
        return [
            'value' => 'google',
            'name' => 'Google Agenda',
            'description' => 'Conecte sua Agenda para que o Agente consulte horários e marque Compromissos.',
            'logo' => 'G',
            'capabilities' => ['Consultar horários', 'Marcar Compromissos', 'Cancelar Compromissos'],
            'planned' => false,
            'available' => true,
        ];
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
            'accountEmail' => $integration->credential('email'),
            'crmAccount' => data_get($integration->crm_metadata, 'account'),
            'crmMetadataLoadedAt' => $integration->crm_metadata_loaded_at?->toIso8601String(),
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
    private function serializeSchedulableCalendar(SchedulableCalendar $calendar): array
    {
        return [
            'id' => $calendar->id,
            'googleCalendarId' => $calendar->google_calendar_id,
            'displayName' => $calendar->display_name,
            'displayDescription' => $calendar->display_description,
            'durationMinutes' => $calendar->duration_minutes,
            'minimumNoticeMinutes' => $calendar->minimum_notice_minutes,
            'businessHours' => $calendar->business_hours,
        ];
    }

    private function serializeShop(Shop $shop): array
    {
        return [
            'id' => $shop->getKey(),
            'domain' => $shop->domain,
            'name' => $shop->name,
            'currencyCode' => $shop->currency_code,
            'countryCode' => $shop->country_code,
            'timezone' => $shop->timezone,
            'syncStatus' => $shop->sync_status,
            'syncStatusLabel' => match ($shop->sync_status) {
                'importing' => 'Importando catálogo',
                'synced' => 'Sincronizada',
                'error' => 'Com erro',
                default => 'Aguardando sincronização',
            },
            'syncProgress' => $shop->sync_progress,
            'syncProcessedCount' => $shop->sync_processed_count,
            'syncTotalCount' => $shop->sync_total_count,
            'productCount' => Product::withoutGlobalScopes()
                ->where('team_id', $shop->team_id)
                ->where('shop_id', $shop->getKey())
                ->count(),
            'customerCount' => StoreCustomer::withoutGlobalScopes()
                ->where('team_id', $shop->team_id)
                ->where('shop_id', $shop->getKey())
                ->count(),
            'orderCount' => StoreOrder::withoutGlobalScopes()
                ->where('team_id', $shop->team_id)
                ->where('shop_id', $shop->getKey())
                ->count(),
            'lastSyncedAt' => $shop->last_synced_at?->toIso8601String(),
            'syncError' => $shop->sync_error,
            'suggestedAction' => $shop->suggested_action,
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
