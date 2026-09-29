<?php

namespace App\Ecommerce;

use App\Ecommerce\Data\EcommerceConnection;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\StartShopifyCatalogImport;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

final class ShopifyEmbeddedInstallationService
{
    public function __construct(
        private ShopifyIdTokenVerifier $idTokenVerifier,
        private ShopifyAdapter $adapter,
    ) {}

    public function authenticate(string $idToken): array
    {
        $domain = $this->idTokenVerifier->verify($idToken);
        $connection = $this->adapter->completeEmbeddedAuthorization($domain, $idToken);
        $result = DB::transaction(function () use ($connection): array {
            $shop = Shop::withoutGlobalScope(TeamScope::class)
                ->with(['integration' => fn ($query) => $query->withoutGlobalScope(TeamScope::class)->withTrashed()])
                ->where('domain', $connection->domain)
                ->lockForUpdate()
                ->first();

            if ($shop !== null) {
                return $this->refreshLinkedShop($shop, $connection);
            }

            return $this->refreshPendingInstallation($connection);
        });

        $homeUrl = URL::temporarySignedRoute(
            'shopify.embedded.home',
            now()->addMinutes(30),
            ['domain' => $connection->domain],
        );

        if (is_string($result['handoff_token'] ?? null)) {
            $homeUrl .= '#handoff='.rawurlencode($result['handoff_token']);
        }

        return [
            'homeUrl' => $homeUrl,
        ];
    }

    private function refreshLinkedShop(Shop $shop, EcommerceConnection $connection): array
    {
        $integration = $shop->integration;
        if ($integration === null || $integration->provider !== 'shopify') {
            throw new RuntimeException('Esta loja já está conectada por outra integração.');
        }

        if ($integration->trashed()) {
            $integration->restore();
        }

        $mustRestartSync = $integration->status === 'error' || $shop->sync_status === 'error';
        $integration->forceFill([
            'credentials' => $connection->credentials,
            'status' => 'connected',
            'status_message' => null,
        ])->save();

        $shop->forceFill([
            'name' => $connection->name,
            'currency_code' => $connection->currencyCode,
            'country_code' => $connection->countryCode,
            'timezone' => $connection->timezone,
        ])->save();

        ShopifyPendingInstallation::query()
            ->where('domain', $connection->domain)
            ->delete();

        if ($mustRestartSync) {
            DB::afterCommit(function () use ($shop): void {
                RegisterShopifyCatalogWebhooks::dispatch($shop->getKey());
                StartShopifyCatalogImport::dispatch($shop->getKey());
            });
        }

        return ['status' => 'connected'];
    }

    private function refreshPendingInstallation(EcommerceConnection $connection): array
    {
        $pending = ShopifyPendingInstallation::query()
            ->where('domain', $connection->domain)
            ->lockForUpdate()
            ->first() ?? new ShopifyPendingInstallation;
        $handoffToken = Str::random(64);

        $pending->fill([
            'domain' => $connection->domain,
            'shop_name' => $connection->name,
            'currency_code' => $connection->currencyCode,
            'country_code' => $connection->countryCode,
            'timezone' => $connection->timezone,
            'credentials' => $connection->credentials,
            'handoff_token_hash' => hash('sha256', $handoffToken),
            'expires_at' => now()->addMinutes(15),
            'handoff_started_at' => null,
            'consumed_at' => null,
        ])->save();

        return [
            'status' => 'pending',
            'handoff_token' => $handoffToken,
        ];
    }
}
