<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TeamSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Show the current team's company settings.
     */
    public function edit(Request $request): Response
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);

        return Inertia::render('settings/company', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'segment' => $team->segment,
                'website' => $team->website,
                'businessDescription' => $team->business_description,
                'productsServices' => $team->products_services,
                'targetAudience' => $team->target_audience,
                'phone' => $team->phone,
                'publicEmail' => $team->public_email,
                'address' => $team->address,
            ],
            'segments' => TeamSegment::options(),
            'canUpdate' => $request->user()->can('update', $team),
        ]);
    }

    /**
     * Update the current team's company settings.
     */
    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);

        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dados da Empresa atualizados.']);

        return to_route('company.edit');
    }
}
