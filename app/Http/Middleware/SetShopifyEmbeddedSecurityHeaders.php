<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetShopifyEmbeddedSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $domain = $request->attributes->get('shopify_verified_domain');

        if (is_string($domain)) {
            $response->headers->set(
                'Content-Security-Policy',
                'frame-ancestors https://'.$domain.' https://admin.shopify.com;',
            );
            $response->headers->remove('X-Frame-Options');
        } elseif ($request->routeIs('shopify.embedded.bootstrap')) {
            $response->headers->set(
                'Content-Security-Policy',
                'frame-ancestors https://admin.shopify.com https://*.myshopify.com;',
            );
            $response->headers->remove('X-Frame-Options');
        }

        if ($request->routeIs('shopify.embedded.bootstrap', 'shopify.embedded.home')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
