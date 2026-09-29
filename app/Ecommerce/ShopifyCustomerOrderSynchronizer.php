<?php

namespace App\Ecommerce;

use App\Enums\ShopifyBulkOperationKind;
use App\Models\ProductVariant;
use App\Models\PurchaseAttribution;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Support\E164PhoneNumber;
use Illuminate\Support\Carbon;
use RuntimeException;

final class ShopifyCustomerOrderSynchronizer
{
    private const CUSTOMER_QUERY = <<<'GRAPHQL'
        query ShopifyCustomerResource($id: ID!) {
          customer(id: $id) {
            id
            firstName
            lastName
            email
            phone
            amountSpent { amount currencyCode }
          }
        }
        GRAPHQL;

    private const ORDER_QUERY = <<<'GRAPHQL'
        query ShopifyOrderResource($id: ID!, $lineItemsAfter: String) {
          order(id: $id) {
            id
            name
            createdAt
            displayFinancialStatus
            displayFulfillmentStatus
            totalPriceSet { shopMoney { amount currencyCode } }
            customer { id }
            customAttributes { key value }
            lineItems(first: 250, after: $lineItemsAfter) {
              nodes {
                id
                title
                quantity
                originalUnitPriceSet { shopMoney { amount } }
                variant { id }
              }
              pageInfo { hasNextPage endCursor }
            }
          }
        }
        GRAPHQL;

    public function sync(Shop $shop, string $resourceType, string $externalId, EcommerceAdapter $adapter): void
    {
        match ($resourceType) {
            'customer' => $this->syncCustomerResource($shop, $externalId, $adapter),
            'order' => $this->syncOrderResource($shop, $externalId, $adapter),
            default => null,
        };
    }

    public function importBulkRecord(
        Shop $shop,
        ShopifyBulkOperationKind $kind,
        mixed $record,
        string $operationId,
    ): void {
        if (
            ! is_array($record)
            || ! is_string($record['__typename'] ?? null)
            || ! is_string($record['id'] ?? null)
        ) {
            throw new RuntimeException('A Shopify retornou uma linha inválida na importação de Clientes ou Pedidos.');
        }

        if ($kind === ShopifyBulkOperationKind::Customers && $record['__typename'] === 'Customer') {
            $this->upsertCustomer($shop, $record, $operationId);

            return;
        }

        if ($kind === ShopifyBulkOperationKind::Orders && $record['__typename'] === 'Order') {
            $this->upsertOrder($shop, $record, $operationId);

            return;
        }

        if ($kind === ShopifyBulkOperationKind::Orders && $record['__typename'] === 'LineItem') {
            $parentId = $record['__parentId'] ?? null;
            $order = is_string($parentId)
                ? StoreOrder::withoutGlobalScopes()
                    ->where('shop_id', $shop->getKey())
                    ->where('external_id', $parentId)
                    ->first()
                : null;

            if ($order === null) {
                throw new RuntimeException('A Shopify retornou um item de pedido sem pedido relacionado.');
            }

            $this->upsertOrderItem($shop, $order, $record, $operationId);

            return;
        }

        throw new RuntimeException('A Shopify retornou um tipo desconhecido na importação de Clientes ou Pedidos.');
    }

    public function finishBulkImport(Shop $shop, ShopifyBulkOperationKind $kind, string $operationId): void
    {
        if ($kind !== ShopifyBulkOperationKind::Orders) {
            return;
        }

        $orderIds = StoreOrder::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('last_import_operation_id', $operationId)
            ->select('id');

        StoreOrderItem::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->whereIn('store_order_id', $orderIds)
            ->where(function ($query) use ($operationId): void {
                $query->whereNull('last_import_operation_id')
                    ->orWhere('last_import_operation_id', '!=', $operationId);
            })
            ->delete();
    }

    private function syncCustomerResource(Shop $shop, string $externalId, EcommerceAdapter $adapter): void
    {
        $result = $adapter->queryShop($shop, self::CUSTOMER_QUERY, ['id' => $externalId]);
        $customerData = data_get($result, 'customer');

        if ($customerData === null) {
            $this->deleteCustomer($shop, $externalId);

            return;
        }

        if (! is_array($customerData)) {
            throw new RuntimeException('A Shopify retornou um Cliente inválido.');
        }

        $this->upsertCustomer($shop, $customerData);
    }

    private function syncOrderResource(Shop $shop, string $externalId, EcommerceAdapter $adapter): void
    {
        $orderData = null;
        $lineItems = [];
        $cursor = null;

        do {
            $result = $adapter->queryShop($shop, self::ORDER_QUERY, [
                'id' => $externalId,
                'lineItemsAfter' => $cursor,
            ]);
            $currentOrderData = data_get($result, 'order');

            if ($currentOrderData === null) {
                if ($orderData === null) {
                    $this->deleteOrder($shop, $externalId);

                    return;
                }

                throw new RuntimeException('A Shopify não conseguiu retornar todas as páginas dos Itens do pedido.');
            }

            if (! is_array($currentOrderData)) {
                throw new RuntimeException('A Shopify retornou um Pedido inválido.');
            }

            $orderData ??= $currentOrderData;
            $currentLineItems = data_get($currentOrderData, 'lineItems.nodes', []);

            if (! is_array($currentLineItems)) {
                throw new RuntimeException('A Shopify retornou Itens do pedido inválidos.');
            }

            $lineItems = [...$lineItems, ...$currentLineItems];
            $pageInfo = data_get($currentOrderData, 'lineItems.pageInfo');
            $hasNextPage = is_array($pageInfo) && data_get($pageInfo, 'hasNextPage') === true;
            $nextCursor = is_array($pageInfo) ? data_get($pageInfo, 'endCursor') : null;

            if ($hasNextPage && ! is_string($nextCursor)) {
                throw new RuntimeException('A Shopify retornou uma página de Itens do pedido sem cursor.');
            }

            $cursor = $hasNextPage ? $nextCursor : null;
        } while ($cursor !== null);

        $customerExternalId = data_get($orderData, 'customer.id');

        if (is_string($customerExternalId)) {
            $hasCustomer = StoreCustomer::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->where('external_id', $customerExternalId)
                ->exists();

            if (! $hasCustomer) {
                $this->syncCustomerResource($shop, $customerExternalId, $adapter);
            }
        }

        $order = $this->upsertOrder($shop, $orderData);

        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem)) {
                throw new RuntimeException('A Shopify retornou um Item do pedido inválido.');
            }

            $this->upsertOrderItem($shop, $order, $lineItem);
        }

        $lineItemIds = collect($lineItems)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();
        $staleItems = StoreOrderItem::withoutGlobalScopes()->where('store_order_id', $order->getKey());

        if ($lineItemIds !== []) {
            $staleItems->whereNotIn('external_id', $lineItemIds);
        }

        $staleItems->delete();
    }

    private function upsertCustomer(Shop $shop, array $customerData, ?string $operationId = null): StoreCustomer
    {
        $externalId = data_get($customerData, 'id');

        if (! is_string($externalId) || $externalId === '') {
            throw new RuntimeException('A Shopify retornou um Cliente sem identificador.');
        }

        $phone = data_get($customerData, 'phone');
        $amountSpent = data_get($customerData, 'amountSpent.amount');
        $currencyCode = data_get($customerData, 'amountSpent.currencyCode');
        $attributes = [
            'team_id' => $shop->team_id,
            'first_name' => is_string(data_get($customerData, 'firstName')) ? data_get($customerData, 'firstName') : null,
            'last_name' => is_string(data_get($customerData, 'lastName')) ? data_get($customerData, 'lastName') : null,
            'email' => is_string(data_get($customerData, 'email')) ? data_get($customerData, 'email') : null,
            'phone_e164' => is_string($phone) ? E164PhoneNumber::formatForCountry($phone, $shop->country_code) : null,
            'total_spent' => is_numeric($amountSpent) ? $amountSpent : 0,
            'currency_code' => is_string($currencyCode) ? $currencyCode : $shop->currency_code,
        ];

        if ($operationId !== null) {
            $attributes['last_import_operation_id'] = $operationId;
        }

        $customer = StoreCustomer::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->getKey(), 'external_id' => $externalId],
            $attributes,
        );

        StoreOrder::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('customer_external_id', $externalId)
            ->update(['store_customer_id' => $customer->getKey()]);

        app(StoreCustomerContactLinker::class)->syncStoreCustomer($customer);

        return $customer;
    }

    private function deleteCustomer(Shop $shop, string $externalId): void
    {
        $customer = StoreCustomer::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $externalId)
            ->first();

        if ($customer === null) {
            return;
        }

        StoreOrder::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('customer_external_id', $externalId)
            ->update(['store_customer_id' => null, 'customer_external_id' => null]);

        $customer->delete();
    }

    private function upsertOrder(Shop $shop, array $orderData, ?string $operationId = null): StoreOrder
    {
        $externalId = data_get($orderData, 'id');
        $orderNumber = data_get($orderData, 'name');
        $amount = data_get($orderData, 'totalPriceSet.shopMoney.amount');
        $currencyCode = data_get($orderData, 'totalPriceSet.shopMoney.currencyCode');
        $orderedAt = data_get($orderData, 'createdAt');
        $customerExternalId = data_get($orderData, 'customer.id');

        if (
            ! is_string($externalId)
            || $externalId === ''
            || ! is_numeric($amount)
            || ! is_string($currencyCode)
            || ! is_string($orderedAt)
        ) {
            throw new RuntimeException('A Shopify retornou um Pedido sem os dados canônicos necessários.');
        }

        $customer = is_string($customerExternalId)
            ? StoreCustomer::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->where('external_id', $customerExternalId)
                ->first()
            : null;
        $attributes = [
            'team_id' => $shop->team_id,
            'store_customer_id' => $customer?->getKey(),
            'customer_external_id' => is_string($customerExternalId) ? $customerExternalId : null,
            'order_number' => is_string($orderNumber) ? $orderNumber : $externalId,
            'total_amount' => $amount,
            'currency_code' => $currencyCode,
            'financial_status' => is_string(data_get($orderData, 'displayFinancialStatus'))
                ? data_get($orderData, 'displayFinancialStatus')
                : null,
            'fulfillment_status' => is_string(data_get($orderData, 'displayFulfillmentStatus'))
                ? data_get($orderData, 'displayFulfillmentStatus')
                : null,
            'ordered_at' => Carbon::parse($orderedAt),
        ];

        if ($operationId !== null) {
            $attributes['last_import_operation_id'] = $operationId;
        }

        $order = StoreOrder::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->getKey(), 'external_id' => $externalId],
            $attributes,
        );

        if ($order->purchase_attribution_id === null) {
            $purchaseAttributionId = $this->purchaseAttributionId($shop, $orderData);

            if ($purchaseAttributionId !== null) {
                StoreOrder::withoutGlobalScopes()
                    ->whereKey($order->getKey())
                    ->whereNull('purchase_attribution_id')
                    ->update(['purchase_attribution_id' => $purchaseAttributionId]);
                $order->refresh();
            }
        }

        return $order;
    }

    private function purchaseAttributionId(Shop $shop, array $orderData): ?string
    {
        $customAttributes = data_get($orderData, 'customAttributes', []);

        if (! is_array($customAttributes)) {
            return null;
        }

        foreach ($customAttributes as $customAttribute) {
            if (! is_array($customAttribute) || data_get($customAttribute, 'key') !== '_platform_attribution') {
                continue;
            }

            $token = data_get($customAttribute, 'value');

            if (! is_string($token) || $token === '') {
                return null;
            }

            $purchaseAttributionId = PurchaseAttribution::withoutGlobalScopes()
                ->where('team_id', $shop->team_id)
                ->where('shop_id', $shop->getKey())
                ->where('token_hash', hash('sha256', $token))
                ->value('id');

            if (is_string($purchaseAttributionId)) {
                return $purchaseAttributionId;
            }
        }

        return null;
    }

    private function deleteOrder(Shop $shop, string $externalId): void
    {
        StoreOrder::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $externalId)
            ->delete();
    }

    private function upsertOrderItem(
        Shop $shop,
        StoreOrder $order,
        array $itemData,
        ?string $operationId = null,
    ): StoreOrderItem {
        $externalId = data_get($itemData, 'id');
        $unitPrice = data_get($itemData, 'originalUnitPriceSet.shopMoney.amount');
        $quantity = data_get($itemData, 'quantity');
        $variantExternalId = data_get($itemData, 'variant.id');

        if (! is_string($externalId) || $externalId === '' || ! is_numeric($unitPrice) || ! is_numeric($quantity)) {
            throw new RuntimeException('A Shopify retornou um Item do pedido sem os dados canônicos necessários.');
        }

        $productVariantId = is_string($variantExternalId)
            ? ProductVariant::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->where('external_id', $variantExternalId)
                ->value('id')
            : null;
        $attributes = [
            'team_id' => $shop->team_id,
            'shop_id' => $shop->getKey(),
            'product_variant_id' => $productVariantId,
            'title' => is_string(data_get($itemData, 'title')) ? data_get($itemData, 'title') : '',
            'quantity' => max(0, (int) $quantity),
            'unit_price' => $unitPrice,
        ];

        if ($operationId !== null) {
            $attributes['last_import_operation_id'] = $operationId;
        }

        return StoreOrderItem::withoutGlobalScopes()->updateOrCreate(
            ['store_order_id' => $order->getKey(), 'external_id' => $externalId],
            $attributes,
        );
    }
}
