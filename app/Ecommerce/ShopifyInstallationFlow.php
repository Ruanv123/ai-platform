<?php

namespace App\Ecommerce;

use App\Models\ShopifyPendingInstallation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ShopifyInstallationFlow
{
    public const SESSION_KEY = 'shopify_install.pending';

    public function pendingForRequest(Request $request): ?ShopifyPendingInstallation
    {
        $context = $request->session()->get(self::SESSION_KEY);
        if (! is_array($context) || ! is_string($context['id'] ?? null) || ! is_string($context['handoff_hash'] ?? null)) {
            return null;
        }

        $pending = ShopifyPendingInstallation::query()
            ->whereKey($context['id'])
            ->where('handoff_token_hash', $context['handoff_hash'])
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pending === null) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $pending;
    }

    public function redirectAfterAuthentication(Request $request): ?RedirectResponse
    {
        if ($this->pendingForRequest($request) === null) {
            return null;
        }

        $user = $request->user();
        if ($user === null) {
            return to_route('login');
        }

        return $user->teams()->exists()
            ? to_route('shopify.install.resume')
            : to_route('onboarding.company');
    }
}
