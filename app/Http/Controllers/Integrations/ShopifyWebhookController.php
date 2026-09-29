<?php

namespace App\Http\Controllers\Integrations;

use App\Ecommerce\EcommerceAdapter;
use App\Http\Controllers\Controller;
use App\Jobs\CompleteShopifyBulkOperation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request, EcommerceAdapter $adapter): Response
    {
        $body = $request->getContent();
        $signature = $request->header('X-Shopify-Hmac-Sha256');

        if (! is_string($signature) || ! $adapter->validateWebhookSignature($body, $signature)) {
            return response()->noContent(Response::HTTP_UNAUTHORIZED);
        }

        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $shop = is_string($shopDomain) && $shopDomain !== ''
            ? $adapter->identifyShopByDomain($shopDomain)
            : null;

        if ($shop === null || $request->header('X-Shopify-Topic') !== 'bulk_operations/finish') {
            return response()->noContent(Response::HTTP_OK);
        }

        $payload = json_decode($body, true);
        $operationId = is_array($payload) ? data_get($payload, 'admin_graphql_api_id') : null;

        if (is_string($operationId) && $operationId !== '') {
            CompleteShopifyBulkOperation::dispatch($shop->getKey(), $operationId);
        }

        return response()->noContent(Response::HTTP_OK);
    }
}
