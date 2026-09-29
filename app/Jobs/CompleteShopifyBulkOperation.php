<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifySyncErrorNotifier;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CompleteShopifyBulkOperation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public array $backoff = [10, 30, 60, 120];

    public int $uniqueFor = 3600;

    private const OPERATION_QUERY = <<<'GRAPHQL'
        query ShopifyBulkOperationResult($id: ID!) {
          bulkOperation(id: $id) {
            id
            status
            objectCount
            url
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
            throw new \RuntimeException('A Shopify não retornou o resultado da importação.');
        }

        if (! in_array($status, ['COMPLETED', 'FINISHED'], true)) {
            if (in_array($status, ['CREATED', 'RUNNING', 'CANCELING'], true)) {
                return;
            }

            $shop->forceFill([
                'sync_status' => 'error',
                'sync_error' => 'A Shopify não conseguiu gerar os dados da Loja para importação.',
                'suggested_action' => 'Tente iniciar a sincronização novamente ou reconecte a Loja.',
            ])->save();
            app(ShopifySyncErrorNotifier::class)->notifyById($this->shopId);

            return;
        }

        $url = data_get($operation, 'url');
        if ($url !== null && ! is_string($url)) {
            throw new \RuntimeException('A Shopify retornou um endereço de importação inválido.');
        }

        ImportShopifyBulkOperation::dispatch($this->shopId, $this->operationId, $url);
    }

    public function failed(?Throwable $exception): void
    {
        $updated = Shop::withoutGlobalScopes()
            ->whereKey($this->shopId)
            ->where('bulk_operation_id', $this->operationId)
            ->where('sync_status', 'importing')
            ->update([
                'sync_status' => 'error',
                'sync_error' => 'Não foi possível buscar o resultado da importação da Shopify.',
                'suggested_action' => 'Tente reconectar a Loja pela tela de Integrações.',
            ]);

        if ($updated > 0) {
            app(ShopifySyncErrorNotifier::class)->notifyById($this->shopId);
        }
    }
}
