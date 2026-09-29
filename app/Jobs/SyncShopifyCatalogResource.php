<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyCatalogSynchronizer;
use App\Ecommerce\ShopifyCustomerOrderSynchronizer;
use App\Enums\EcommerceProvider;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SyncShopifyCatalogResource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public array $backoff = [5, 15, 30];

    public int $uniqueFor = 30;

    public function __construct(
        public string $shopId,
        public string $resourceType,
        public string $externalId,
        public bool $deleted,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->shopId, $this->resourceType, $this->externalId]);
    }

    public function handle(
        EcommerceAdapter $adapter,
        ShopifyCatalogSynchronizer $synchronizer,
        ShopifyCustomerOrderSynchronizer $customerOrderSynchronizer,
    ): void {
        $shop = Shop::withoutGlobalScope(TeamScope::class)
            ->with('integration')
            ->whereKey($this->shopId)
            ->first();

        if ($shop === null || $shop->integration?->provider !== EcommerceProvider::Shopify->value) {
            return;
        }

        if (! $this->deleted && $shop->integration->status !== 'connected') {
            return;
        }

        if (in_array($this->resourceType, ['customer', 'order'], true)) {
            $customerOrderSynchronizer->sync($shop, $this->resourceType, $this->externalId, $adapter);

            return;
        }

        $synchronizer->sync($shop, $this->resourceType, $this->externalId, $adapter);
    }
}
