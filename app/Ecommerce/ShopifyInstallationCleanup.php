<?php

namespace App\Ecommerce;

use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ShopifyInstallationCleanup
{
    public function clearMirrorForReplacement(Shop $shop): ?Integration
    {
        return DB::transaction(function () use ($shop): ?Integration {
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

            DB::table('products')->where('shop_id', $lockedShop->getKey())->delete();
            DB::table('categories')->where('shop_id', $lockedShop->getKey())->delete();
            $lockedShop->delete();

            if ($integration !== null) {
                $integration->forceFill([
                    'credentials' => [],
                    'status' => 'disconnected',
                    'status_message' => null,
                ])->save();
                $integration->delete();
            }

            $disabledActions = [
                'search_products' => false,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('agents', 'send_purchase_link')) {
                $disabledActions['send_purchase_link'] = false;
            }

            DB::table('agents')->where('team_id', $lockedShop->team_id)->update($disabledActions);

            return $integration;
        });
    }

    public function removeAfterUninstall(Shop $shop): void
    {
        $this->clearMirrorForReplacement($shop);
    }
}
