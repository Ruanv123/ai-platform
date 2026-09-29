<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyReconciliationSynchronizer;
use App\Enums\EcommerceProvider;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileShopifyShop implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public array $backoff = [30, 60, 120, 300];

    public int $timeout = 300;

    public int $uniqueFor = 86400;

    public function __construct(public string $shopId) {}

    public function uniqueId(): string
    {
        return $this->shopId;
    }

    public function handle(EcommerceAdapter $adapter, ShopifyReconciliationSynchronizer $synchronizer): void
    {
        $shop = Shop::withoutGlobalScope(TeamScope::class)
            ->with('integration')
            ->whereKey($this->shopId)
            ->first();

        if (
            $shop === null
            || $shop->integration?->provider !== EcommerceProvider::Shopify->value
            || $shop->integration->status !== 'connected'
            || $shop->last_synced_at === null
            || ! in_array($shop->sync_status, ['synced', 'error'], true)
        ) {
            return;
        }

        $synchronizer->reconcile($shop, $adapter);
    }
}
