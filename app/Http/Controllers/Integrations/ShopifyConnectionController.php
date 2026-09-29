<?php

namespace App\Http\Controllers\Integrations;

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyInstallationCleanup;
use App\Enums\EcommerceProvider;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\StartShopifyCatalogImport;
use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ShopifyConnectionController extends Controller
{
    private const CALLBACK_STATE_SESSION_KEY = 'shopify_oauth.pending';

    private const STATE_LIFETIME_SECONDS = 600;

    public function connect(Request $request, EcommerceAdapter $adapter): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($adapter->provider() === EcommerceProvider::Shopify, 404);

        $data = $request->validate([
            'shop' => ['required', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com\z/i'],
        ]);
        $domain = strtolower($data['shop']);
        $state = Str::random(64);
        $team = $request->user()->company();

        $request->session()->put(self::CALLBACK_STATE_SESSION_KEY.'.'.$state, [
            'team_id' => $team->getKey(),
            'domain' => $domain,
            'expires_at' => now()->addSeconds(self::STATE_LIFETIME_SECONDS)->timestamp,
        ]);

        try {
            return redirect()->away($adapter->authorizationUrl($domain, $state));
        } catch (RuntimeException $exception) {
            $request->session()->forget(self::CALLBACK_STATE_SESSION_KEY.'.'.$state);

            return $this->redirectToShopifySettings()->with('shopify_error', $exception->getMessage());
        }
    }

    public function callback(Request $request, EcommerceAdapter $adapter, ShopifyInstallationCleanup $installationCleanup): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($adapter->provider() === EcommerceProvider::Shopify, 404);

        $state = $request->query('state');
        if (! is_string($state) || $state === '') {
            return $this->failed('A autorização da Shopify expirou. Tente conectar novamente.');
        }

        $pending = $request->session()->pull(self::CALLBACK_STATE_SESSION_KEY.'.'.$state);
        if (! is_array($pending) || ! is_numeric($pending['expires_at'] ?? null) || (int) $pending['expires_at'] < now()->timestamp) {
            return $this->failed('A autorização da Shopify expirou. Tente conectar novamente.');
        }

        $team = $request->user()->company();
        if ($team === null || (int) ($pending['team_id'] ?? 0) !== $team->getKey()) {
            return $this->failed('A conexão precisa voltar à mesma Empresa que iniciou a autorização.');
        }

        if ($request->filled('error')) {
            return $this->failed('A autorização da Shopify foi recusada.');
        }

        $parameters = $request->query();
        if (! $adapter->validateAuthorizationCallback($parameters)) {
            return $this->failed('A resposta da Shopify não pôde ser validada. Tente conectar novamente.');
        }

        $domain = $request->query('shop');
        $timestamp = $request->query('timestamp');
        $code = $request->query('code');

        if (! is_string($domain) || ! is_string($pending['domain'] ?? null) || strtolower($domain) !== strtolower($pending['domain'])) {
            return $this->failed('A autorização voltou para uma loja diferente da que iniciou a conexão.');
        }

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > self::STATE_LIFETIME_SECONDS) {
            return $this->failed('A resposta da Shopify expirou. Tente conectar novamente.');
        }

        if (! is_string($code) || $code === '') {
            return $this->failed('A Shopify não retornou o código de autorização. Tente conectar novamente.');
        }

        try {
            $connection = $adapter->completeAuthorization($domain, $code);
            $shopBelongsToAnotherTeam = Shop::withoutGlobalScope(TeamScope::class)
                ->where('domain', $connection->domain)
                ->where('team_id', '!=', $team->getKey())
                ->exists();

            if ($shopBelongsToAnotherTeam) {
                return $this->failed('Esta loja já está conectada a outra Empresa.');
            }

            $existingShop = Shop::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->where('team_id', $team->getKey())
                ->first();
            if ($existingShop !== null
                && ! $existingShop->trashed()
                && $existingShop->domain !== $connection->domain
                && $existingShop->integration?->status === 'connected') {
                $adapter->uninstallApp($existingShop);
                $installationCleanup->clearMirrorForReplacement($existingShop);
            }

            DB::transaction(function () use ($team, $connection): void {
                $integration = Integration::withTrashed()
                    ->where('team_id', $team->getKey())
                    ->where('type', 'ecommerce')
                    ->lockForUpdate()
                    ->first();
                $integration ??= $team->integrations()->make();

                if ($integration->trashed()) {
                    $integration->restore();
                }

                $integration->forceFill([
                    'name' => $connection->name,
                    'type' => 'ecommerce',
                    'provider' => EcommerceProvider::Shopify->value,
                    'credentials' => $connection->credentials,
                    'status' => 'connected',
                    'status_message' => null,
                    'last_tested_at' => now(),
                ])->save();

                $shop = Shop::withTrashed()
                    ->where('team_id', $team->getKey())
                    ->lockForUpdate()
                    ->first();
                $shop ??= $team->shops()->make();
                if ($shop->trashed()) {
                    $shop->restore();
                }

                $shop->forceFill([
                    'team_id' => $team->getKey(),
                    'integration_id' => $integration->getKey(),
                    'domain' => $connection->domain,
                    'name' => $connection->name,
                    'currency_code' => $connection->currencyCode,
                    'country_code' => $connection->countryCode,
                    'timezone' => $connection->timezone,
                    'sync_status' => 'importing',
                    'sync_progress' => 0,
                    'bulk_operation_id' => null,
                    'sync_processed_count' => 0,
                    'sync_total_count' => null,
                    'sync_error' => null,
                    'suggested_action' => null,
                ])->save();
            });

            $shop = $team->shops()->firstOrFail();
            RegisterShopifyCatalogWebhooks::dispatch($shop->getKey());
            StartShopifyCatalogImport::dispatch($shop->getKey());

            return $this->redirectToShopifySettings()->with('shopify_success', 'Loja Shopify conectada.');
        } catch (Throwable $exception) {
            report($exception);

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Não foi possível conectar a Shopify. Tente novamente.';

            return $this->failed($message);
        }
    }

    private function authorizeManagement(Request $request): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
    }

    private function redirectToShopifySettings(): RedirectResponse
    {
        return to_route('integrations.show', ['type' => 'ecommerce', 'provider' => EcommerceProvider::Shopify->value]);
    }

    private function failed(string $message): RedirectResponse
    {
        return $this->redirectToShopifySettings()->with('shopify_error', $message);
    }
}
