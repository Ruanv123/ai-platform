<?php

namespace App\Ecommerce;

use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use Illuminate\Support\Facades\DB;

final class ShopifyInstallationCleanup
{
    public function clearMirrorForReplacement(Shop $shop): ?Integration
    {
        return $this->clearMirror($shop, false);
    }

    public function removeAfterUninstall(Shop $shop): void
    {
        $this->clearMirrorForReplacement($shop);
    }

    public function removeAfterUninstallByDomain(string $domain): void
    {
        $shop = $this->shopByDomain($domain);

        if ($shop !== null) {
            $this->removeAfterUninstall($shop);
        }
    }

    public function redactShopByDomain(string $domain): void
    {
        $shop = $this->shopByDomain($domain);

        if ($shop === null) {
            ShopifyPendingInstallation::query()->where('domain', strtolower($domain))->delete();

            return;
        }

        $this->clearMirror($shop, true);
        ShopifyPendingInstallation::query()->where('domain', strtolower($domain))->delete();
    }

    private function clearMirror(Shop $shop, bool $redactMetadata): ?Integration
    {
        return DB::transaction(function () use ($shop, $redactMetadata): ?Integration {
            $lockedShop = Shop::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->whereKey($shop->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedShop === null) {
                return null;
            }

            $integration = Integration::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->whereKey($lockedShop->integration_id)
                ->where('team_id', $lockedShop->team_id)
                ->lockForUpdate()
                ->first();

            DB::table('store_order_items')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('store_orders')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('purchase_attributions')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('store_customers')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('product_variants')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('category_product')
                ->whereIn('product_id', DB::table('products')->select('id')->where('shop_id', $lockedShop->getKey()))
                ->delete();
            DB::table('products')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('categories')->where('shop_id', $lockedShop->getKey())->delete();

            DB::table('agents')->where('team_id', $lockedShop->team_id)->update([
                'search_products' => false,
                'send_purchase_link' => false,
                'shop_tools_enabled' => false,
                'updated_at' => now(),
            ]);

            if ($integration !== null) {
                $integration->forceFill([
                    'credentials' => [],
                    'status' => 'disconnected',
                    'status_message' => null,
                ])->save();
            }

            if ($redactMetadata) {
                $lockedShop->forceDelete();
                $integration?->forceDelete();

                return $integration;
            }

            if (! $lockedShop->trashed()) {
                $lockedShop->delete();
            }

            if ($integration !== null && ! $integration->trashed()) {
                $integration->delete();
            }

            return $integration;
        });
    }

    private function shopByDomain(string $domain): ?Shop
    {
        return Shop::withoutGlobalScope(TeamScope::class)
            ->withTrashed()
            ->where('domain', strtolower($domain))
            ->first();
    }
}
