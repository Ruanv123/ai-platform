<?php

namespace App\Http\Controllers\Integrations;

use App\Ecommerce\InvalidShopifyIdTokenException;
use App\Ecommerce\ShopifyEmbeddedInstallationService;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

final class ShopifyEmbeddedController extends Controller
{
    public function bootstrap(): InertiaResponse
    {
        return Inertia::render('shopify/embedded-bootstrap', [
            'authenticateUrl' => route('shopify.embedded.authenticate'),
        ]);
    }

    public function authenticate(Request $request, ShopifyEmbeddedInstallationService $installationService): JsonResponse
    {
        $idToken = $request->bearerToken();
        if (! is_string($idToken) || $idToken === '') {
            return $this->invalidSession();
        }

        try {
            return response()->json($installationService->authenticate($idToken));
        } catch (InvalidShopifyIdTokenException) {
            return $this->invalidSession();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Não foi possível autenticar a loja Shopify.'], 502);
        }
    }

    public function home(Request $request): InertiaResponse
    {
        $domain = $request->query('domain');
        abort_unless(is_string($domain) && preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/', $domain) === 1, 404);

        $request->attributes->set('shopify_verified_domain', $domain);
        $shop = Shop::withoutGlobalScope(TeamScope::class)
            ->with('team')
            ->where('domain', $domain)
            ->first();

        if ($shop !== null) {
            return Inertia::render('shopify/embedded-home', [
                'status' => 'connected',
                'shop' => [
                    'domain' => $shop->domain,
                    'name' => $shop->name,
                    'teamName' => $shop->team->name,
                    'syncStatus' => $shop->sync_status,
                    'syncProgress' => $shop->sync_progress,
                    'lastSyncedAt' => $shop->last_synced_at?->toIso8601String(),
                    'syncError' => $shop->sync_error,
                    'suggestedAction' => $shop->suggested_action,
                ],
                'platformUrl' => route('integrations.show', [
                    'type' => 'ecommerce',
                    'provider' => 'shopify',
                ]),
            ]);
        }

        $pendingInstallation = ShopifyPendingInstallation::query()
            ->where('domain', $domain)
            ->first();

        return Inertia::render('shopify/embedded-home', [
            'status' => $pendingInstallation?->isActive() ? 'pending' : 'expired',
            'shop' => [
                'domain' => $domain,
                'name' => $pendingInstallation?->shop_name,
                'teamName' => null,
                'syncStatus' => null,
                'syncProgress' => null,
                'lastSyncedAt' => null,
                'syncError' => null,
                'suggestedAction' => null,
            ],
            'platformUrl' => null,
        ]);
    }

    private function invalidSession(): JsonResponse
    {
        return response()->json(['message' => 'A sessão da Shopify expirou. Abra o app novamente.'], 401, [
            'X-Shopify-Retry-Invalid-Session-Request' => '1',
        ]);
    }
}
