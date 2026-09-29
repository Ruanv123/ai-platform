<?php

namespace App\Ecommerce;

use App\Jobs\SyncShopifyCatalogResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ShopifyCatalogSynchronizer
{
    private const PRODUCT_QUERY = <<<'GRAPHQL'
        query ShopifyProductCatalogResource($id: ID!, $variantsAfter: String, $collectionsAfter: String) {
          product(id: $id) {
            id
            title
            descriptionHtml
            handle
            status
            onlineStoreUrl
            variants(first: 250, after: $variantsAfter) {
              nodes {
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
              pageInfo { hasNextPage endCursor }
            }
            collections(first: 250, after: $collectionsAfter) {
              nodes { id title }
              pageInfo { hasNextPage endCursor }
            }
          }
        }
        GRAPHQL;

    private const INVENTORY_ITEM_QUERY = <<<'GRAPHQL'
        query ShopifyInventoryItemVariant($id: ID!) {
          inventoryItem(id: $id) {
            id
            variants(first: 1) {
              nodes {
                id
                inventoryQuantity
                availableForSale
                product { id }
              }
            }
          }
        }
        GRAPHQL;

    private const COLLECTION_QUERY = <<<'GRAPHQL'
        query ShopifyCollectionCatalogResource($id: ID!, $after: String) {
          collection(id: $id) {
            id
            title
            products(first: 250, after: $after) {
              nodes { id }
              pageInfo { hasNextPage endCursor }
            }
          }
        }
        GRAPHQL;

    public function __construct(private ProductEmbeddingSynchronizer $embeddingSynchronizer) {}

    public function sync(Shop $shop, string $resourceType, string $externalId, EcommerceAdapter $adapter): void
    {
        match ($resourceType) {
            'product' => $this->syncProduct($shop, $externalId, $adapter),
            'inventory_item' => $this->syncInventoryItem($shop, $externalId, $adapter),
            'collection' => $this->syncCollection($shop, $externalId, $adapter),
            default => null,
        };
    }

    private function syncProduct(Shop $shop, string $externalId, EcommerceAdapter $adapter): void
    {
        $productData = $this->fetchProduct($shop, $externalId, $adapter);

        if ($productData === null) {
            $this->deleteProduct($shop, $externalId);

            return;
        }

        $productId = DB::transaction(function () use ($shop, $externalId, $productData): string {
            $productAttributes = [
                'team_id' => $shop->team_id,
                'title' => $productData['title'],
                'description' => $this->plainText($productData['descriptionHtml']),
                'handle' => $productData['handle'],
                'url' => $productData['onlineStoreUrl'],
                'status' => $productData['status'],
                'online_store_published' => is_string($productData['onlineStoreUrl']) && $productData['onlineStoreUrl'] !== '',
            ];
            $activeOperationId = $this->activeImportOperationId($shop);
            if ($activeOperationId !== null) {
                $productAttributes['last_import_operation_id'] = $activeOperationId;
            }

            $product = Product::withoutGlobalScopes()->updateOrCreate(
                ['shop_id' => $shop->getKey(), 'external_id' => $externalId],
                $productAttributes,
            );

            $variantIds = [];
            foreach ($productData['variants'] as $variantData) {
                $variantIds[] = $variantData['id'];
                $variantAttributes = [
                    'team_id' => $shop->team_id,
                    'product_id' => $product->getKey(),
                    'sku' => $variantData['sku'],
                    'title' => $variantData['title'],
                    'options' => $this->selectedOptions($variantData['selectedOptions']),
                    'price' => $variantData['price'],
                    'compare_at_price' => $variantData['compareAtPrice'],
                    'inventory_quantity' => is_numeric($variantData['inventoryQuantity']) ? (int) $variantData['inventoryQuantity'] : 0,
                    'continues_selling_when_out_of_stock' => $variantData['inventoryPolicy'] === 'CONTINUE',
                    'available' => $variantData['availableForSale'],
                ];
                if ($activeOperationId !== null) {
                    $variantAttributes['last_import_operation_id'] = $activeOperationId;
                }

                ProductVariant::withoutGlobalScopes()->updateOrCreate(
                    ['shop_id' => $shop->getKey(), 'external_id' => $variantData['id']],
                    $variantAttributes,
                );
            }

            $staleVariants = ProductVariant::withoutGlobalScopes()->where('product_id', $product->getKey());
            if ($variantIds !== []) {
                $staleVariants->whereNotIn('external_id', $variantIds);
            }
            $staleVariants->delete();

            $categoryIds = [];
            foreach ($productData['collections'] as $collectionData) {
                $category = Category::withoutGlobalScopes()->updateOrCreate(
                    ['shop_id' => $shop->getKey(), 'external_id' => $collectionData['id']],
                    ['team_id' => $shop->team_id, 'title' => $collectionData['title']],
                );
                $categoryIds[] = $category->getKey();
                $pivot = [
                    'team_id' => $shop->team_id,
                    'product_id' => $product->getKey(),
                    'category_id' => $category->getKey(),
                    'last_import_operation_id' => $activeOperationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $updatedPivotColumns = ['team_id', 'updated_at'];
                if ($activeOperationId !== null) {
                    $updatedPivotColumns[] = 'last_import_operation_id';
                }

                DB::table('category_product')->upsert(
                    [$pivot],
                    ['product_id', 'category_id'],
                    $updatedPivotColumns,
                );
            }

            $categoryLinks = DB::table('category_product')->where('product_id', $product->getKey());
            if ($categoryIds !== []) {
                $categoryLinks->whereNotIn('category_id', $categoryIds);
            }
            $categoryLinks->delete();

            return $product->getKey();
        });

        $this->deleteEmptyCategories($shop);
        $this->embeddingSynchronizer->refresh($productId);
    }

    private function syncInventoryItem(Shop $shop, string $externalId, EcommerceAdapter $adapter): void
    {
        $result = $adapter->queryShop($shop, self::INVENTORY_ITEM_QUERY, ['id' => $externalId]);
        $inventoryItem = data_get($result, 'inventoryItem');
        $variant = is_array($inventoryItem) ? data_get($inventoryItem, 'variants.nodes.0') : null;

        if (! is_array($variant)) {
            return;
        }

        $variantId = data_get($variant, 'id');
        $inventoryQuantity = data_get($variant, 'inventoryQuantity');
        $available = data_get($variant, 'availableForSale');
        $productId = data_get($variant, 'product.id');

        if (! is_string($variantId) || ! is_bool($available)) {
            throw new RuntimeException('A Shopify não retornou o estoque atual da Variante.');
        }

        $updatedVariants = ProductVariant::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $variantId)
            ->update([
                'inventory_quantity' => is_numeric($inventoryQuantity) ? (int) $inventoryQuantity : 0,
                'available' => $available,
            ]);

        if ($updatedVariants === 0 && is_string($productId)) {
            SyncShopifyCatalogResource::dispatch($shop->getKey(), 'product', $productId, false)
                ->delay(now()->addSeconds(3));
        }
    }

    private function syncCollection(Shop $shop, string $externalId, EcommerceAdapter $adapter): void
    {
        $collectionData = $this->fetchCollection($shop, $externalId, $adapter);

        if ($collectionData === null) {
            $this->deleteCollection($shop, $externalId);

            return;
        }

        $affectedProductIds = DB::transaction(function () use ($shop, $externalId, $collectionData): array {
            $category = Category::withoutGlobalScopes()->updateOrCreate(
                ['shop_id' => $shop->getKey(), 'external_id' => $externalId],
                ['team_id' => $shop->team_id, 'title' => $collectionData['title']],
            );
            $previousProductIds = DB::table('category_product as category_links')
                ->join('products', 'products.id', '=', 'category_links.product_id')
                ->where('category_links.category_id', $category->getKey())
                ->pluck('products.external_id')
                ->all();
            $currentProductIds = array_values(array_unique($collectionData['productIds']));
            $activeOperationId = $this->activeImportOperationId($shop);

            foreach (array_chunk($currentProductIds, 250) as $productIds) {
                $products = Product::withoutGlobalScopes()
                    ->where('shop_id', $shop->getKey())
                    ->whereIn('external_id', $productIds)
                    ->get(['id']);

                $rows = $products->map(fn (Product $product): array => [
                    'team_id' => $shop->team_id,
                    'product_id' => $product->getKey(),
                    'category_id' => $category->getKey(),
                    'last_import_operation_id' => $activeOperationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if ($rows !== []) {
                    $updatedPivotColumns = ['team_id', 'updated_at'];
                    if ($activeOperationId !== null) {
                        $updatedPivotColumns[] = 'last_import_operation_id';
                    }

                    DB::table('category_product')->upsert(
                        $rows,
                        ['product_id', 'category_id'],
                        $updatedPivotColumns,
                    );
                }
            }

            $currentProductIdLookup = array_fill_keys($currentProductIds, true);
            $staleProductIds = array_values(array_filter(
                $previousProductIds,
                fn (string $productId): bool => ! isset($currentProductIdLookup[$productId]),
            ));
            foreach (array_chunk($staleProductIds, 250) as $productIds) {
                $localProductIds = Product::withoutGlobalScopes()
                    ->where('shop_id', $shop->getKey())
                    ->whereIn('external_id', $productIds)
                    ->pluck('id');

                if ($localProductIds->isNotEmpty()) {
                    DB::table('category_product')
                        ->where('category_id', $category->getKey())
                        ->whereIn('product_id', $localProductIds)
                        ->delete();
                }
            }

            return array_values(array_unique(array_merge($previousProductIds, $currentProductIds)));
        });

        foreach ($affectedProductIds as $productId) {
            SyncShopifyCatalogResource::dispatch($shop->getKey(), 'product', $productId, false)
                ->delay(now()->addSeconds(3));
        }
    }

    private function fetchProduct(Shop $shop, string $externalId, EcommerceAdapter $adapter): ?array
    {
        $variantsAfter = null;
        $collectionsAfter = null;
        $productRecord = null;
        $variants = [];
        $collections = [];
        $variantsComplete = false;
        $collectionsComplete = false;

        do {
            $result = $adapter->queryShop($shop, self::PRODUCT_QUERY, [
                'id' => $externalId,
                'variantsAfter' => $variantsAfter,
                'collectionsAfter' => $collectionsAfter,
            ]);
            $page = data_get($result, 'product');

            if ($page === null) {
                return null;
            }

            if (! is_array($page) || data_get($page, 'id') !== $externalId) {
                throw new RuntimeException('A Shopify não retornou o Produto solicitado.');
            }

            $productRecord = $page;
            $variantNodes = data_get($page, 'variants.nodes');
            $collectionNodes = data_get($page, 'collections.nodes');
            if (! is_array($variantNodes) || ! is_array($collectionNodes)) {
                throw new RuntimeException('A Shopify retornou uma página inválida do Produto.');
            }

            if (! $variantsComplete) {
                foreach ($variantNodes as $variant) {
                    if (! is_array($variant)
                        || ! is_string($variant['id'] ?? null)
                        || ! is_string($variant['title'] ?? null)
                        || ! is_numeric($variant['price'] ?? null)
                        || ! is_bool($variant['availableForSale'] ?? null)) {
                        throw new RuntimeException('A Shopify retornou uma Variante inválida.');
                    }

                    $variants[] = [
                        'id' => $variant['id'],
                        'sku' => is_string($variant['sku'] ?? null) ? $variant['sku'] : null,
                        'title' => $variant['title'],
                        'price' => (string) $variant['price'],
                        'compareAtPrice' => is_numeric($variant['compareAtPrice'] ?? null) ? (string) $variant['compareAtPrice'] : null,
                        'inventoryQuantity' => $variant['inventoryQuantity'] ?? 0,
                        'inventoryPolicy' => is_string($variant['inventoryPolicy'] ?? null) ? $variant['inventoryPolicy'] : '',
                        'availableForSale' => $variant['availableForSale'],
                        'selectedOptions' => is_array($variant['selectedOptions'] ?? null) ? $variant['selectedOptions'] : [],
                    ];
                }

                $variantsPageInfo = data_get($page, 'variants.pageInfo');
                $variantsHasNextPage = data_get($variantsPageInfo, 'hasNextPage') === true;
                $variantsAfter = $variantsHasNextPage ? data_get($variantsPageInfo, 'endCursor') : null;
                $variantsComplete = ! $variantsHasNextPage;

                if ($variantsHasNextPage && ! is_string($variantsAfter)) {
                    throw new RuntimeException('A Shopify retornou uma página incompleta do Produto.');
                }
            }

            if (! $collectionsComplete) {
                foreach ($collectionNodes as $collection) {
                    if (! is_array($collection) || ! is_string($collection['id'] ?? null) || ! is_string($collection['title'] ?? null)) {
                        throw new RuntimeException('A Shopify retornou uma Categoria inválida.');
                    }

                    $collections[] = ['id' => $collection['id'], 'title' => $collection['title']];
                }

                $collectionsPageInfo = data_get($page, 'collections.pageInfo');
                $collectionsHasNextPage = data_get($collectionsPageInfo, 'hasNextPage') === true;
                $collectionsAfter = $collectionsHasNextPage ? data_get($collectionsPageInfo, 'endCursor') : null;
                $collectionsComplete = ! $collectionsHasNextPage;

                if ($collectionsHasNextPage && ! is_string($collectionsAfter)) {
                    throw new RuntimeException('A Shopify retornou uma página incompleta do Produto.');
                }
            }
        } while ($variantsAfter !== null || $collectionsAfter !== null);

        if (! is_array($productRecord)
            || ! is_string($productRecord['title'] ?? null)
            || ! is_string($productRecord['descriptionHtml'] ?? null)
            || ! is_string($productRecord['handle'] ?? null)
            || ! is_string($productRecord['status'] ?? null)
            || (! is_string($productRecord['onlineStoreUrl'] ?? null) && ($productRecord['onlineStoreUrl'] ?? null) !== null)) {
            throw new RuntimeException('A Shopify retornou dados inválidos do Produto.');
        }

        return [
            'title' => $productRecord['title'],
            'descriptionHtml' => $productRecord['descriptionHtml'],
            'handle' => $productRecord['handle'],
            'status' => $productRecord['status'],
            'onlineStoreUrl' => $productRecord['onlineStoreUrl'] ?? null,
            'variants' => $variants,
            'collections' => $collections,
        ];
    }

    private function fetchCollection(Shop $shop, string $externalId, EcommerceAdapter $adapter): ?array
    {
        $after = null;
        $collectionRecord = null;
        $productIds = [];

        do {
            $result = $adapter->queryShop($shop, self::COLLECTION_QUERY, [
                'id' => $externalId,
                'after' => $after,
            ]);
            $page = data_get($result, 'collection');

            if ($page === null) {
                return null;
            }

            if (! is_array($page) || data_get($page, 'id') !== $externalId || ! is_string($page['title'] ?? null)) {
                throw new RuntimeException('A Shopify não retornou a Categoria solicitada.');
            }

            $collectionRecord = $page;
            $products = data_get($page, 'products.nodes');
            $pageInfo = data_get($page, 'products.pageInfo');
            if (! is_array($products)) {
                throw new RuntimeException('A Shopify retornou uma página inválida da Categoria.');
            }

            foreach ($products as $product) {
                if (! is_array($product) || ! is_string($product['id'] ?? null)) {
                    throw new RuntimeException('A Shopify retornou um Produto inválido na Categoria.');
                }

                $productIds[] = $product['id'];
            }

            $hasNextPage = data_get($pageInfo, 'hasNextPage') === true;
            $after = $hasNextPage ? data_get($pageInfo, 'endCursor') : null;

            if ($hasNextPage && ! is_string($after)) {
                throw new RuntimeException('A Shopify retornou uma página incompleta da Categoria.');
            }
        } while ($after !== null);

        return is_array($collectionRecord)
            ? ['title' => $collectionRecord['title'], 'productIds' => $productIds]
            : null;
    }

    private function deleteProduct(Shop $shop, string $externalId): void
    {
        Product::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $externalId)
            ->delete();

        $this->deleteEmptyCategories($shop);
    }

    private function deleteCollection(Shop $shop, string $externalId): void
    {
        $category = Category::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $externalId)
            ->first();

        if ($category === null) {
            return;
        }

        $productIds = DB::table('category_product as category_links')
            ->join('products', 'products.id', '=', 'category_links.product_id')
            ->where('category_links.category_id', $category->getKey())
            ->pluck('products.external_id')
            ->all();

        $category->delete();

        foreach ($productIds as $productId) {
            SyncShopifyCatalogResource::dispatch($shop->getKey(), 'product', $productId, false)
                ->delay(now()->addSeconds(3));
        }
    }

    private function deleteEmptyCategories(Shop $shop): void
    {
        DB::table('categories as catalog_categories')
            ->where('catalog_categories.shop_id', $shop->getKey())
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('category_product as category_links')
                    ->whereColumn('category_links.category_id', 'catalog_categories.id');
            })
            ->delete();
    }

    private function activeImportOperationId(Shop $shop): ?string
    {
        return $shop->sync_status === 'importing' && is_string($shop->bulk_operation_id)
            ? $shop->bulk_operation_id
            : null;
    }

    private function selectedOptions(array $selectedOptions): array
    {
        return collect($selectedOptions)
            ->filter(fn (mixed $option): bool => is_array($option)
                && is_string($option['name'] ?? null)
                && is_string($option['value'] ?? null))
            ->take(3)
            ->map(fn (array $option): array => ['name' => $option['name'], 'value' => $option['value']])
            ->values()
            ->all();
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
