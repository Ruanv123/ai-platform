<?php

namespace App\Jobs;

use App\Ecommerce\ProductEmbeddingSynchronizer;
use App\Ecommerce\ShopifyCustomerOrderSynchronizer;
use App\Enums\ShopifyBulkOperationKind;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ImportShopifyBulkOperation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 120, 300];

    public int $timeout = 1800;

    public int $uniqueFor = 86400;

    public function __construct(public string $shopId, public string $operationId, public ?string $url) {}

    public function uniqueId(): string
    {
        return $this->shopId.':'.$this->operationId;
    }

    public function handle(
        ProductEmbeddingSynchronizer $embeddingSynchronizer,
        ShopifyCustomerOrderSynchronizer $customerOrderSynchronizer,
    ): void {
        $shop = Shop::withoutGlobalScopes()->whereKey($this->shopId)->first();

        if ($shop === null || $shop->sync_status !== 'importing' || $shop->bulk_operation_id !== $this->operationId) {
            return;
        }

        $kind = ShopifyBulkOperationKind::tryFrom($shop->bulk_operation_kind ?? '')
            ?? ShopifyBulkOperationKind::Catalog;
        $processedCount = 0;
        if (is_string($this->url)) {
            $processedCount = $this->streamJsonl($shop, $kind, $customerOrderSynchronizer);
        }

        if ($kind === ShopifyBulkOperationKind::Catalog) {
            $this->refreshProductEmbeddings($shop, $embeddingSynchronizer);
            $this->removeStaleCatalogRecords($shop);
        } else {
            $customerOrderSynchronizer->finishBulkImport($shop, $kind, $this->operationId);
        }

        $nextKind = $kind->next();
        $nextImportKind = DB::transaction(function () use ($processedCount, $kind, $nextKind): ?ShopifyBulkOperationKind {
            $shop = Shop::withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($this->shopId)
                ->where('bulk_operation_id', $this->operationId)
                ->first();

            if ($shop === null || $shop->sync_status !== 'importing') {
                return null;
            }

            $storedKind = $shop->bulk_operation_kind ?? ShopifyBulkOperationKind::Catalog->value;

            if ($storedKind !== $kind->value) {
                return null;
            }

            if ($nextKind !== null) {
                $shop->forceFill([
                    'bulk_operation_id' => null,
                    'bulk_operation_kind' => $nextKind->value,
                    'sync_processed_count' => 0,
                    'sync_total_count' => null,
                    'sync_progress' => max($shop->sync_progress, $nextKind->initialProgress()),
                ])->save();

                return $nextKind;
            }

            $shop->forceFill([
                'sync_status' => 'synced',
                'sync_progress' => 100,
                'sync_processed_count' => $processedCount,
                'last_synced_at' => now(),
                'bulk_operation_id' => null,
                'bulk_operation_kind' => null,
                'sync_error' => null,
                'suggested_action' => null,
            ])->save();

            return null;
        });

        if ($nextImportKind !== null) {
            StartShopifyCustomerOrderImport::dispatch($this->shopId, $nextImportKind);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Shop::withoutGlobalScopes()
            ->whereKey($this->shopId)
            ->where('bulk_operation_id', $this->operationId)
            ->where('sync_status', 'importing')
            ->update([
                'sync_status' => 'error',
                'sync_error' => 'Não foi possível importar os dados da Loja Shopify.',
                'suggested_action' => 'Tente reconectar a Loja pela tela de Integrações.',
            ]);
    }

    private function streamJsonl(
        Shop $shop,
        ShopifyBulkOperationKind $kind,
        ShopifyCustomerOrderSynchronizer $customerOrderSynchronizer,
    ): int {
        $response = Http::withOptions(['stream' => true])
            ->connectTimeout(10)
            ->timeout(1800)
            ->get($this->url);

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível baixar o arquivo da importação Shopify.');
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $processedCount = 0;

        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);

            while (($newlinePosition = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePosition));
                $buffer = substr($buffer, $newlinePosition + 1);

                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if ($kind === ShopifyBulkOperationKind::Catalog) {
                    $this->processCatalogLine($shop, $record);
                } else {
                    $customerOrderSynchronizer->importBulkRecord($shop, $kind, $record, $this->operationId);
                }
                $processedCount++;

                if ($processedCount % 100 === 0) {
                    $this->updateImportProgress($shop, $processedCount);
                }
            }
        }

        $line = trim($buffer);
        if ($line !== '') {
            $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if ($kind === ShopifyBulkOperationKind::Catalog) {
                $this->processCatalogLine($shop, $record);
            } else {
                $customerOrderSynchronizer->importBulkRecord($shop, $kind, $record, $this->operationId);
            }
            $processedCount++;
        }

        $this->updateImportProgress($shop, $processedCount);

        return $processedCount;
    }

    private function processCatalogLine(Shop $shop, mixed $record): void
    {
        if (! is_array($record) || ! is_string($record['__typename'] ?? null) || ! is_string($record['id'] ?? null)) {
            throw new RuntimeException('A Shopify retornou uma linha inválida no catálogo.');
        }

        if ($record['__typename'] === 'Product') {
            $descriptionHtml = $record['descriptionHtml'] ?? '';
            $description = is_string($descriptionHtml) ? $this->plainText($descriptionHtml) : '';
            $onlineStoreUrl = $record['onlineStoreUrl'] ?? null;

            Product::withoutGlobalScopes()->updateOrCreate(
                [
                    'shop_id' => $shop->getKey(),
                    'external_id' => $record['id'],
                ],
                [
                    'team_id' => $shop->team_id,
                    'title' => is_string($record['title'] ?? null) ? $record['title'] : '',
                    'description' => $description,
                    'handle' => is_string($record['handle'] ?? null) ? $record['handle'] : '',
                    'url' => is_string($onlineStoreUrl) ? $onlineStoreUrl : null,
                    'status' => is_string($record['status'] ?? null) ? $record['status'] : 'UNKNOWN',
                    'online_store_published' => is_string($onlineStoreUrl) && $onlineStoreUrl !== '',
                    'last_import_operation_id' => $this->operationId,
                ],
            );

            return;
        }

        $parentId = $record['__parentId'] ?? null;
        if (! is_string($parentId) || $parentId === '') {
            throw new RuntimeException('A Shopify retornou um item de catálogo sem produto relacionado.');
        }

        $product = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $parentId)
            ->first();

        if ($product === null) {
            throw new RuntimeException('A Shopify retornou um item de catálogo sem produto relacionado.');
        }

        if ($record['__typename'] === 'ProductVariant') {
            $this->upsertVariant($shop, $product, $record);

            return;
        }

        if ($record['__typename'] === 'Collection') {
            $this->upsertCategory($shop, $product, $record);

            return;
        }

        throw new RuntimeException('A Shopify retornou um tipo de item de catálogo desconhecido.');
    }

    private function upsertVariant(Shop $shop, Product $product, array $record): void
    {
        $options = collect(is_array($record['selectedOptions'] ?? null) ? $record['selectedOptions'] : [])
            ->filter(fn (mixed $option): bool => is_array($option)
                && is_string($option['name'] ?? null)
                && is_string($option['value'] ?? null))
            ->take(3)
            ->map(fn (array $option): array => ['name' => $option['name'], 'value' => $option['value']])
            ->values()
            ->all();
        $price = $record['price'] ?? null;
        $compareAtPrice = $record['compareAtPrice'] ?? null;

        if (! is_numeric($price)) {
            throw new RuntimeException('A Shopify retornou uma variante sem preço válido.');
        }

        ProductVariant::withoutGlobalScopes()->updateOrCreate(
            [
                'shop_id' => $shop->getKey(),
                'external_id' => $record['id'],
            ],
            [
                'team_id' => $shop->team_id,
                'product_id' => $product->getKey(),
                'sku' => is_string($record['sku'] ?? null) ? $record['sku'] : null,
                'title' => is_string($record['title'] ?? null) ? $record['title'] : '',
                'options' => $options,
                'price' => $price,
                'compare_at_price' => is_numeric($compareAtPrice) ? $compareAtPrice : null,
                'inventory_quantity' => (int) ($record['inventoryQuantity'] ?? 0),
                'continues_selling_when_out_of_stock' => ($record['inventoryPolicy'] ?? null) === 'CONTINUE',
                'available' => ($record['availableForSale'] ?? false) === true,
                'last_import_operation_id' => $this->operationId,
            ],
        );
    }

    private function upsertCategory(Shop $shop, Product $product, array $record): void
    {
        $category = Category::withoutGlobalScopes()->updateOrCreate(
            [
                'shop_id' => $shop->getKey(),
                'external_id' => $record['id'],
            ],
            [
                'team_id' => $shop->team_id,
                'title' => is_string($record['title'] ?? null) ? $record['title'] : '',
            ],
        );

        DB::table('category_product')->upsert(
            [[
                'team_id' => $shop->team_id,
                'product_id' => $product->getKey(),
                'category_id' => $category->getKey(),
                'last_import_operation_id' => $this->operationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['product_id', 'category_id'],
            ['team_id', 'last_import_operation_id', 'updated_at'],
        );
    }

    private function refreshProductEmbeddings(Shop $shop, ProductEmbeddingSynchronizer $embeddingSynchronizer): void
    {
        Product::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('last_import_operation_id', $this->operationId)
            ->chunkById(100, function (Collection $products) use ($embeddingSynchronizer): void {
                foreach ($products as $product) {
                    $embeddingSynchronizer->refresh($product->getKey());
                }
            });
    }

    private function removeStaleCatalogRecords(Shop $shop): void
    {
        ProductVariant::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where(function ($query): void {
                $query->whereNull('last_import_operation_id')
                    ->orWhere('last_import_operation_id', '!=', $this->operationId);
            })
            ->delete();

        DB::table('category_product')
            ->whereIn('product_id', Product::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->select('id'))
            ->where(function ($query): void {
                $query->whereNull('last_import_operation_id')
                    ->orWhere('last_import_operation_id', '!=', $this->operationId);
            })
            ->delete();

        Product::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where(function ($query): void {
                $query->whereNull('last_import_operation_id')
                    ->orWhere('last_import_operation_id', '!=', $this->operationId);
            })
            ->delete();

        Category::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->whereDoesntHave('products')
            ->delete();
    }

    private function updateImportProgress(Shop $shop, int $processedCount): void
    {
        $kind = ShopifyBulkOperationKind::tryFrom($shop->bulk_operation_kind ?? '')
            ?? ShopifyBulkOperationKind::Catalog;
        $progress = $kind->progress($processedCount, $shop->sync_total_count);

        Shop::withoutGlobalScopes()
            ->whereKey($shop->getKey())
            ->where('bulk_operation_id', $this->operationId)
            ->where('sync_status', 'importing')
            ->update([
                'sync_processed_count' => $processedCount,
                'sync_progress' => $progress,
            ]);
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
