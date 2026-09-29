<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PollShopifyBulkOperation implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public array $backoff = [10, 30, 60, 120];

    public int $uniqueFor = 3600;

    private const OPERATION_QUERY = <<<'GRAPHQL'
        query ShopifyBulkOperationStatus($id: ID!) {
          bulkOperation(id: $id) {
            id
            status
            objectCount
          }
        }
        GRAPHQL;

    public function __construct(public string $shopId, public string $operationId) {}

    public function uniqueId(): string
    {
        return $this->shopId.':'.$this->operationId;
    }

    public function handle(EcommerceAdapter $adapter): void
    {
        $shop = Shop::withoutGlobalScopes()->whereKey($this->shopId)->first();

        if ($shop === null || $shop->sync_status !== 'importing' || $shop->bulk_operation_id !== $this->operationId) {
            return;
        }

        $result = $adapter->queryShop($shop, self::OPERATION_QUERY, ['id' => $this->operationId]);
        $operation = data_get($result, 'bulkOperation');
        $status = is_array($operation) ? data_get($operation, 'status') : null;

        if (! is_array($operation) || ! is_string($status)) {
            throw new \RuntimeException('A Shopify não retornou o andamento da importação.');
        }

        $processedCount = max(0, (int) data_get($operation, 'objectCount', 0));
        $totalCount = $shop->sync_total_count;
        $progress = $totalCount === null || $totalCount < 1
            ? 0
            : min(95, (int) floor(($processedCount / $totalCount) * 95));

        $shop->forceFill([
            'sync_processed_count' => $processedCount,
            'sync_progress' => max($shop->sync_progress, $progress),
        ])->save();

        if (in_array($status, ['CREATED', 'RUNNING', 'CANCELING'], true)) {
            self::dispatch($this->shopId, $this->operationId)->delay(now()->addSeconds(5));

            return;
        }

        if (in_array($status, ['COMPLETED', 'FINISHED'], true)) {
            CompleteShopifyBulkOperation::dispatch($this->shopId, $this->operationId);

            return;
        }

        $shop->forceFill([
            'sync_status' => 'error',
            'sync_error' => 'A Shopify não conseguiu gerar o catálogo para importação.',
            'suggested_action' => 'Tente iniciar a sincronização novamente ou reconecte a Loja.',
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Shop::withoutGlobalScopes()
            ->whereKey($this->shopId)
            ->where('bulk_operation_id', $this->operationId)
            ->where('sync_status', 'importing')
            ->update([
                'sync_status' => 'error',
                'sync_error' => 'Não foi possível consultar o andamento da importação Shopify.',
                'suggested_action' => 'Tente reconectar a Loja pela tela de Integrações.',
            ]);
    }
}
