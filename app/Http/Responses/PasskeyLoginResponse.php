<?php

namespace App\Http\Responses;

use App\Ecommerce\ShopifyInstallationFlow;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(private ShopifyInstallationFlow $shopifyInstallationFlow) {}

    public function toResponse($request): Response
    {
        $installationRedirect = $this->shopifyInstallationFlow->redirectAfterAuthentication($request);
        if ($installationRedirect !== null) {
            return $request->wantsJson()
                ? new JsonResponse(['redirect' => $installationRedirect->getTargetUrl()], 200)
                : $installationRedirect;
        }

        $redirect = Fortify::redirects('login');

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => redirect()->intended($redirect)->getTargetUrl()], 200)
            : redirect()->intended($redirect);
    }
}
