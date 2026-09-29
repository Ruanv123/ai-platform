<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileShopifyShop;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('shopify:reconcile')]
#[Description('Reconcile Shopify shops from the canonical mirror cursor')]
class ReconcileShopifyShops extends Command
{
    public function handle(): int
    {
        Shop::withoutGlobalScope(TeamScope::class)
            ->whereNotNull('last_synced_at')
            ->whereIn('sync_status', ['synced', 'error'])
            ->whereHas('integration', function (Builder $query): void {
                $query->where('provider', 'shopify')->where('status', 'connected');
            })
            ->chunkById(100, function (Collection $shops): void {
                foreach ($shops as $shop) {
                    ReconcileShopifyShop::dispatch($shop->getKey());
                }
            });

        $this->info('A reconciliação das Lojas Shopify foi enfileirada.');

        return self::SUCCESS;
    }
}
