<?php

namespace App\Http\Controllers\Integrations;

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyInstallationCleanup;
use App\Http\Controllers\Controller;
use App\Jobs\CompleteShopifyBulkOperation;
use App\Jobs\SyncShopifyCatalogResource;
use App\Models\ShopifyPendingInstallation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopifyWebhookController extends Controller
{
    private const RESOURCE_TOPICS = [
        'products/create' => ['product', 'Product', false, 'id'],
        'products/update' => ['product', 'Product', false, 'id'],
        'products/delete' => ['product', 'Product', true, 'id'],
        'inventory_levels/update' => ['inventory_item', 'InventoryItem', false, 'inventory_item_id'],
        'collections/create' => ['collection', 'Collection', false, 'id'],
        'collections/update' => ['collection', 'Collection', false, 'id'],
        'collections/delete' => ['collection', 'Collection', true, 'id'],
    ];

    public function handle(
        Request $request,
        EcommerceAdapter $adapter,
        ShopifyInstallationCleanup $installationCleanup,
    ): Response {
        $body = $request->getContent();
        $signature = $request->header('X-Shopify-Hmac-Sha256');

        if (! is_string($signature) || ! $adapter->validateWebhookSignature($body, $signature)) {
            return response()->noContent(Response::HTTP_UNAUTHORIZED);
        }

        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $topic = $request->header('X-Shopify-Topic');

        if ($topic === 'app/uninstalled') {
            if (is_string($shopDomain) && preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/i', $shopDomain) === 1) {
                $shop = $adapter->identifyShopByDomain($shopDomain);
                if ($shop !== null) {
                    $installationCleanup->removeAfterUninstall($shop);
                }

                ShopifyPendingInstallation::query()->where('domain', strtolower($shopDomain))->delete();
            }

            return response()->noContent(Response::HTTP_OK);
        }

        $shop = is_string($shopDomain) && $shopDomain !== ''
            ? $adapter->identifyShopByDomain($shopDomain)
            : null;

        if ($shop === null) {
            return response()->noContent(Response::HTTP_OK);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->noContent(Response::HTTP_OK);
        }

        if ($topic === 'bulk_operations/finish') {
            $operationId = data_get($payload, 'admin_graphql_api_id');

            if (is_string($operationId) && $operationId !== '') {
                CompleteShopifyBulkOperation::dispatch($shop->getKey(), $operationId);
            }

            return response()->noContent(Response::HTTP_OK);
        }

        $topicDetails = is_string($topic) ? (self::RESOURCE_TOPICS[$topic] ?? null) : null;
        if ($topicDetails === null) {
            return response()->noContent(Response::HTTP_OK);
        }

        [$resourceType, $shopifyType, $deleted, $idField] = $topicDetails;
        $externalId = $this->externalId($payload, $shopifyType, $idField);

        if ($externalId !== null) {
            SyncShopifyCatalogResource::dispatch($shop->getKey(), $resourceType, $externalId, $deleted)
                ->delay(now()->addSeconds(3));
        }

        return response()->noContent(Response::HTTP_OK);
    }

    private function externalId(array $payload, string $shopifyType, string $idField): ?string
    {
        $id = data_get($payload, 'admin_graphql_api_id');
        if (is_string($id) && preg_match('/\Agid:\/\/shopify\/'.$shopifyType.'\/\d+\z/', $id) === 1) {
            return $id;
        }

        $id = data_get($payload, $idField);
        if (is_string($id) && preg_match('/\Agid:\/\/shopify\/'.$shopifyType.'\/\d+\z/', $id) === 1) {
            return $id;
        }

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            return 'gid://shopify/'.$shopifyType.'/'.(string) $id;
        }

        return null;
    }
}
