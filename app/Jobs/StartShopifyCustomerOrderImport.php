<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Enums\EcommerceProvider;
use App\Enums\ShopifyBulkOperationKind;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class StartShopifyCustomerOrderImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public array $backoff = [10, 30, 60, 120];

    public int $uniqueFor = 3600;

    private const COUNTS_QUERY = <<<'GRAPHQL'
        query ShopifyCustomerOrderCounts($orderQuery: String!) {
          customersCount(limit: null) { count }
          ordersCount(query: $orderQuery, limit: null) { count }
        }
        GRAPHQL;

    private const START_MUTATION = <<<'GRAPHQL'
        mutation StartShopifyCustomerOrderImport($query: String!) {
          bulkOperationRunQuery(query: $query) {
            bulkOperation { id status }
            userErrors { field message }
          }
        }
        GRAPHQL;

    public function __construct(
        public string $shopId,
        public ShopifyBulkOperationKind $kind,
    ) {}

    public function uniqueId(): string
    {
        return $this->shopId.':'.$this->kind->value;
    }

    public function handle(EcommerceAdapter $adapter): void
    {
        $shop = Shop::withoutGlobalScope(TeamScope::class)
            ->with('integration')
            ->whereKey($this->shopId)
            ->first();

        if (
            $shop === null
            || $shop->integration?->provider !== EcommerceProvider::Shopify->value
            || $shop->integration->status !== 'connected'
            || $shop->sync_status !== 'importing'
            || $shop->bulk_operation_id !== null
            || $shop->bulk_operation_kind !== $this->kind->value
            || ! in_array($this->kind, [ShopifyBulkOperationKind::Customers, ShopifyBulkOperationKind::Orders], true)
        ) {
            return;
        }

        $orderFilter = 'created_at:>='.$this->ordersCutoff();
        $counts = $adapter->queryShop($shop, self::COUNTS_QUERY, ['orderQuery' => $orderFilter]);
        $totalCount = $this->kind === ShopifyBulkOperationKind::Customers
            ? data_get($counts, 'customersCount.count')
            : data_get($counts, 'ordersCount.count');
        $totalCount = is_numeric($totalCount) ? (int) $totalCount : 0;

        $result = $adapter->queryShop($shop, self::START_MUTATION, [
            'query' => $this->bulkQuery($orderFilter),
        ]);
        $bulkOperation = data_get($result, 'bulkOperationRunQuery.bulkOperation');
        $userErrors = data_get($result, 'bulkOperationRunQuery.userErrors', []);
        $operationId = is_array($bulkOperation) ? data_get($bulkOperation, 'id') : null;

        if (! is_string($operationId) || $operationId === '' || ! is_array($userErrors) || $userErrors !== []) {
            throw new RuntimeException('A Shopify não conseguiu iniciar a importação de Clientes ou Pedidos.');
        }

        $updatedShop = DB::transaction(function () use ($operationId, $totalCount): ?Shop {
            $lockedShop = Shop::withoutGlobalScope(TeamScope::class)
                ->lockForUpdate()
                ->whereKey($this->shopId)
                ->first();

            if (
                $lockedShop === null
                || $lockedShop->sync_status !== 'importing'
                || $lockedShop->bulk_operation_id !== null
                || $lockedShop->bulk_operation_kind !== $this->kind->value
            ) {
                return null;
            }

            $lockedShop->forceFill([
                'bulk_operation_id' => $operationId,
                'sync_processed_count' => 0,
                'sync_total_count' => $totalCount > 0 ? $totalCount : null,
                'sync_progress' => max($lockedShop->sync_progress, $this->kind->initialProgress()),
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
        Shop::withoutGlobalScope(TeamScope::class)
            ->whereKey($this->shopId)
            ->where('bulk_operation_kind', $this->kind->value)
            ->where('sync_status', 'importing')
            ->update([
                'sync_status' => 'error',
                'sync_error' => 'Não foi possível iniciar a importação de Clientes ou Pedidos da Shopify.',
                'suggested_action' => 'Tente reconectar a Loja pela tela de Integrações.',
            ]);
    }

    private function ordersCutoff(): string
    {
        return now()->subDays(60)->toDateString();
    }

    private function bulkQuery(string $orderFilter): string
    {
        if ($this->kind === ShopifyBulkOperationKind::Customers) {
            return <<<'GRAPHQL'
                {
                  customers {
                    edges {
                      node {
                        __typename
                        id
                        firstName
                        lastName
                        email
                        phone
                        amountSpent { amount currencyCode }
                      }
                    }
                  }
                }
                GRAPHQL;
        }

        $escapedFilter = addcslashes($orderFilter, '\\"');

        return <<<GRAPHQL
            {
              orders(query: "{$escapedFilter}") {
                edges {
                  node {
                    __typename
                    id
                    name
                    createdAt
                    displayFinancialStatus
                    displayFulfillmentStatus
                    totalPriceSet { shopMoney { amount currencyCode } }
                    customer { id }
                    lineItems {
                      edges {
                        node {
                          __typename
                          id
                          title
                          quantity
                          originalUnitPriceSet { shopMoney { amount } }
                          variant { id }
                        }
                      }
                    }
                  }
                }
              }
            }
            GRAPHQL;
    }
}
