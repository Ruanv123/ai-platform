<?php

namespace App\Http\Responses;

use App\Ecommerce\ShopifyInstallationFlow;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private ShopifyInstallationFlow $shopifyInstallationFlow) {}

    public function toResponse($request): Response
    {
        $installationRedirect = $this->shopifyInstallationFlow->redirectAfterAuthentication($request);
        if ($installationRedirect !== null) {
            return $installationRedirect;
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 201)
            : redirect()->intended(
                $request->user()?->company() === null
                    ? route('onboarding.company')
                    : Fortify::redirects('register'),
            );
    }
}
