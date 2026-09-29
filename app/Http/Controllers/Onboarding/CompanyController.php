<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Teams\CreateTeam;
use App\Ecommerce\ShopifyInstallationFlow;
use App\Enums\TeamSegment;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Show the company onboarding form.
     */
    public function create(): Response
    {
        return Inertia::render('onboarding/company', [
            'segments' => TeamSegment::options(),
            'timezones' => timezone_identifiers_list(),
            'weekdays' => Weekday::options(),
        ]);
    }

    /**
     * Create the company and make the authenticated user its owner.
     */
    public function store(
        SaveCompanyRequest $request,
        CreateTeam $createTeam,
        ShopifyInstallationFlow $shopifyInstallationFlow,
    ): RedirectResponse {
        $user = $request->user();

        abort_if($user?->company() !== null, 403, 'Você já pertence a uma Empresa.');

        $validated = $request->validated();
        $createTeam->handle($user, $validated['name'], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa criada com sucesso.']);

        $installationRedirect = $shopifyInstallationFlow->redirectAfterAuthentication($request);
        if ($installationRedirect !== null) {
            return $installationRedirect;
        }

        return to_route('dashboard');
    }
}
