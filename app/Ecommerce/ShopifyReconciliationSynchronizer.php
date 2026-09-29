<?php

namespace App\Ecommerce;

use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Models\Category;
use App\Models\Product;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;

class ShopifyReconciliationSynchronizer
{
    private const NODE_BATCH_SIZE = 250;

    private const NODES_QUERY = <<<'GRAPHQL'
        query ShopifyReconciliationNodes($ids: [ID!]!) {
          nodes(ids: $ids) { id }
        }
        GRAPHQL;

    private const RESOURCE_QUERIES = [
        'product' => <<<'GRAPHQL'
            query ShopifyReconciliationProducts($query: String!, $after: String) {
              products(first: 100, after: $after, query: $query) {
                nodes { id }
                pageInfo { hasNextPage endCursor }
              }
            }
            GRAPHQL,
        'collection' => <<<'GRAPHQL'
            query ShopifyReconciliationCollections($query: String!, $after: String) {
              collections(first: 100, after: $after, query: $query) {
                nodes { id }
                pageInfo { hasNextPage endCursor }
              }
            }
            GRAPHQL,
        'customer' => <<<'GRAPHQL'
            query ShopifyReconciliationCustomers($query: String!, $after: String) {
              customers(first: 100, after: $after, query: $query) {
                nodes { id }
                pageInfo { hasNextPage endCursor }
              }
            }
            GRAPHQL,
        'order' => <<<'GRAPHQL'
            query ShopifyReconciliationOrders($query: String!, $after: String) {
              orders(first: 100, after: $after, query: $query) {
                nodes { id }
                pageInfo { hasNextPage endCursor }
              }
            }
            GRAPHQL,
    ];

    public function __construct(
        private ShopifyCatalogSynchronizer $catalogSynchronizer,
        private ShopifyCustomerOrderSynchronizer $customerOrderSynchronizer,
    ) {}

    public function reconcile(Shop $shop, EcommerceAdapter $adapter): void
    {
        $cursor = $shop->reconciliation_cursor ?? $shop->last_synced_at;
        if ($cursor === null) {
            return;
        }

        $reconciledAt = now()->toImmutable();
        RegisterShopifyCatalogWebhooks::dispatch($shop->getKey());

        foreach (array_keys(self::RESOURCE_QUERIES) as $resourceType) {
            $this->syncUpdatedResources($shop, $resourceType, $cursor, $reconciledAt, $adapter);
        }

        $this->removeMissingResources($shop, $reconciledAt, $adapter);

        DB::transaction(function () use ($shop, $reconciledAt): void {
            $lockedShop = Shop::withoutGlobalScope(TeamScope::class)
                ->lockForUpdate()
                ->whereKey($shop->getKey())
                ->first();

            if ($lockedShop === null || $lockedShop->integration_id !== $shop->integration_id) {
                return;
            }

            $integrationStatus = $lockedShop->integration()->withTrashed()->value('status');
            if ($integrationStatus !== 'connected') {
                return;
            }

            $lockedShop->forceFill([
                'sync_status' => 'synced',
                'last_synced_at' => $reconciledAt,
                'reconciliation_cursor' => $reconciledAt,
                'sync_error' => null,
                'suggested_action' => null,
            ])->save();
        });
    }

    private function syncUpdatedResources(
        Shop $shop,
        string $resourceType,
        \DateTimeInterface $cursor,
        \DateTimeInterface $reconciledAt,
        EcommerceAdapter $adapter,
    ): void {
        $connection = match ($resourceType) {
            'product' => 'products',
            'collection' => 'collections',
            'customer' => 'customers',
            'order' => 'orders',
            default => throw new RuntimeException('Tipo de recurso Shopify inválido para reconciliação.'),
        };
        $filters = [
            'updated_at:>="'.$cursor->format(DATE_ATOM).'"',
            'updated_at:<="'.$reconciledAt->format(DATE_ATOM).'"',
        ];

        if ($resourceType === 'order') {
            $ordersCutoff = \DateTimeImmutable::createFromInterface($reconciledAt)
                ->modify('-60 days')
                ->format(DATE_ATOM);
            $filters[] = 'created_at:>="'.$ordersCutoff.'"';
        }

        $queryFilter = implode(' AND ', $filters);
        $after = null;

        do {
            $result = $adapter->queryShop($shop, self::RESOURCE_QUERIES[$resourceType], [
                'query' => $queryFilter,
                'after' => $after,
            ]);
            $nodes = data_get($result, $connection.'.nodes');
            $pageInfo = data_get($result, $connection.'.pageInfo');

            if (! is_array($nodes) || ! is_array($pageInfo)) {
                throw new RuntimeException('A Shopify retornou uma página inválida durante a reconciliação.');
            }

            foreach ($nodes as $node) {
                $externalId = is_array($node) ? data_get($node, 'id') : null;
                if (! is_string($externalId) || $externalId === '') {
                    throw new RuntimeException('A Shopify retornou um recurso sem identificador durante a reconciliação.');
                }

                $this->syncResource($shop, $resourceType, $externalId, $adapter);
            }

            $hasNextPage = data_get($pageInfo, 'hasNextPage') === true;
            $nextCursor = data_get($pageInfo, 'endCursor');
            if ($hasNextPage && (! is_string($nextCursor) || $nextCursor === '')) {
                throw new RuntimeException('A Shopify retornou uma página sem cursor durante a reconciliação.');
            }

            $after = $hasNextPage ? $nextCursor : null;
        } while ($after !== null);
    }

    private function removeMissingResources(Shop $shop, \DateTimeInterface $reconciledAt, EcommerceAdapter $adapter): void
    {
        foreach ([
            Product::class => 'product',
            Category::class => 'collection',
            StoreCustomer::class => 'customer',
            StoreOrder::class => 'order',
        ] as $modelClass => $resourceType) {
            $query = $modelClass::withoutGlobalScopes()->where('shop_id', $shop->getKey());
            if ($modelClass === StoreOrder::class) {
                $ordersCutoff = \DateTimeImmutable::createFromInterface($reconciledAt)->modify('-60 days');
                $query->where('ordered_at', '>=', $ordersCutoff);
            }

            $query->select(['id', 'external_id'])
                ->lazyById(self::NODE_BATCH_SIZE, 'id')
                ->chunk(self::NODE_BATCH_SIZE)
                ->each(function (LazyCollection $records) use ($adapter, $resourceType, $shop): void {
                    $externalIds = $records->pluck('external_id')->filter(fn (mixed $externalId): bool => is_string($externalId) && $externalId !== '')->values();
                    if ($externalIds->isEmpty()) {
                        return;
                    }

                    $result = $adapter->queryShop($shop, self::NODES_QUERY, ['ids' => $externalIds->all()]);
                    $nodes = data_get($result, 'nodes');
                    if (! is_array($nodes)) {
                        throw new RuntimeException('A Shopify retornou uma lista inválida de recursos durante a reconciliação.');
                    }

                    $existingIds = collect($nodes)
                        ->filter(fn (mixed $node): bool => is_array($node) && is_string(data_get($node, 'id')))
                        ->pluck('id')
                        ->flip();

                    foreach ($externalIds as $externalId) {
                        if (! $existingIds->has($externalId)) {
                            $this->syncResource($shop, $resourceType, $externalId, $adapter);
                        }
                    }
                });
        }
    }

    private function syncResource(Shop $shop, string $resourceType, string $externalId, EcommerceAdapter $adapter): void
    {
        if (in_array($resourceType, ['customer', 'order'], true)) {
            $this->customerOrderSynchronizer->sync($shop, $resourceType, $externalId, $adapter);

            return;
        }

        $this->catalogSynchronizer->sync($shop, $resourceType, $externalId, $adapter);
    }
}
