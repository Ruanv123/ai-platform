<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifySyncErrorNotifier;
use App\Enums\ShopifyBulkOperationKind;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StartShopifyCatalogImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public array $backoff = [10, 30, 60, 120];

    public int $uniqueFor = 3600;

    private const COUNTS_QUERY = <<<'GRAPHQL'
        query ShopifyCatalogCounts {
          productsCount(limit: null) { count }
          productVariantsCount(limit: null) { count }
          collectionsCount(limit: null) { count }
        }
        GRAPHQL;

    private const BULK_QUERY = <<<'GRAPHQL'
        {
          products {
            edges {
              node {
                __typename
                id
                title
                descriptionHtml
                handle
                status
                onlineStoreUrl
                variants {
                  edges {
                    node {
                      __typename
                      id
                      sku
                      title
                      price
                      compareAtPrice
                      inventoryQuantity
                      inventoryPolicy
                      availableForSale
                      selectedOptions { name value }
                    }
                  }
                }
                collections {
                  edges {
                    node { __typename id title }
                  }
                }
              }
            }
          }
        }
        GRAPHQL;

    private const START_MUTATION = <<<'GRAPHQL'
        mutation StartShopifyCatalogImport($query: String!) {
          bulkOperationRunQuery(query: $query) {
            bulkOperation { id status }
            userErrors { field message }
          }
        }
        GRAPHQL;

    public function __construct(public string $shopId) {}

    public function uniqueId(): string
    {
        return $this->shopId;
    }

    public function handle(EcommerceAdapter $adapter): void
    {
        $shop = Shop::withoutGlobalScopes()->whereKey($this->shopId)->first();

        if (
            $shop === null
            || $shop->sync_status !== 'importing'
            || $shop->bulk_operation_id !== null
            || ! in_array($shop->bulk_operation_kind, [null, ShopifyBulkOperationKind::Catalog->value], true)
        ) {
            return;
        }

        $counts = $adapter->queryShop($shop, self::COUNTS_QUERY);
        $totalCount = collect([
            data_get($counts, 'productsCount.count'),
            data_get($counts, 'productVariantsCount.count'),
            data_get($counts, 'collectionsCount.count'),
        ])->filter(fn (mixed $count): bool => is_numeric($count))
            ->sum(fn (mixed $count): int => (int) $count);

        $result = $adapter->queryShop($shop, self::START_MUTATION, ['query' => self::BULK_QUERY]);
        $bulkOperation = data_get($result, 'bulkOperationRunQuery.bulkOperation');
        $userErrors = data_get($result, 'bulkOperationRunQuery.userErrors', []);
        $operationId = is_array($bulkOperation) ? data_get($bulkOperation, 'id') : null;

        if (! is_string($operationId) || $operationId === '' || ! is_array($userErrors) || $userErrors !== []) {
            throw new RuntimeException('A Shopify não conseguiu iniciar a importação do catálogo.');
        }

        $updatedShop = DB::transaction(function () use ($operationId, $totalCount): ?Shop {
            $lockedShop = Shop::withoutGlobalScopes()->lockForUpdate()->whereKey($this->shopId)->first();

            if (
                $lockedShop === null
                || $lockedShop->sync_status !== 'importing'
                || $lockedShop->bulk_operation_id !== null
                || ! in_array($lockedShop->bulk_operation_kind, [null, ShopifyBulkOperationKind::Catalog->value], true)
            ) {
                return null;
            }

            $lockedShop->forceFill([
                'bulk_operation_id' => $operationId,
                'bulk_operation_kind' => ShopifyBulkOperationKind::Catalog->value,
                'sync_processed_count' => 0,
                'sync_total_count' => $totalCount > 0 ? $totalCount : null,
                'sync_progress' => 0,
            ])->save();

            return $lockedShop;
        });

        if ($updatedShop !== null) {
            PollShopifyBulkOperation::dispatch($this->shopId, $operationId)
                ->delay(now()->addSeconds(5));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $updated = Shop::withoutGlobalScopes()
            ->whereKey($this->shopId)
            ->where('sync_status', 'importing')
            ->where(function ($query): void {
                $query->whereNull('bulk_operation_kind')
                    ->orWhere('bulk_operation_kind', ShopifyBulkOperationKind::Catalog->value);
            })
            ->update([
                'sync_status' => 'error',
                'bulk_operation_kind' => ShopifyBulkOperationKind::Catalog->value,
                'sync_error' => 'Não foi possível iniciar a importação do catálogo Shopify.',
                'suggested_action' => 'Tente reconectar a Loja pela tela de Integrações.',
            ]);

        if ($updated > 0) {
            app(ShopifySyncErrorNotifier::class)->notifyById($this->shopId);
        }
    }
}
