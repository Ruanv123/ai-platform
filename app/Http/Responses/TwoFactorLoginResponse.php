<?php

namespace App\Http\Responses;

use App\Ecommerce\ShopifyInstallationFlow;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(private ShopifyInstallationFlow $shopifyInstallationFlow) {}

    public function toResponse($request): Response
    {
        $invitationCode = $request->input('invitation') ?? $request->session()->pull('team_invitation_code');

        if (is_string($invitationCode) && $request->user()?->company() === null) {
            return redirect()->route('invitations.show', [
                'invitation' => $invitationCode,
            ]);
        }

        $installationRedirect = $this->shopifyInstallationFlow->redirectAfterAuthentication($request);
        if ($installationRedirect !== null) {
            return $installationRedirect;
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended(Fortify::redirects('login'));
    }
}
