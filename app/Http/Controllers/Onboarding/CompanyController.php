<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamSegment;
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
            'segments' => self::segments(),
            'timezones' => timezone_identifiers_list(),
            'weekdays' => self::weekdays(),
        ]);
    }

    /**
     * Create the company and make the authenticated user its owner.
     */
    public function store(SaveCompanyRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $user = $request->user();

        abort_if($user?->company() !== null, 403, 'Você já pertence a uma Empresa.');

        $validated = $request->validated();
        $createTeam->handle($user, $validated['name'], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa criada com sucesso.']);

        return to_route('dashboard');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function segments(): array
    {
        return TeamSegment::options();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function weekdays(): array
    {
        return [
            ['value' => 'monday', 'label' => 'Segunda-feira'],
            ['value' => 'tuesday', 'label' => 'Terça-feira'],
            ['value' => 'wednesday', 'label' => 'Quarta-feira'],
            ['value' => 'thursday', 'label' => 'Quinta-feira'],
            ['value' => 'friday', 'label' => 'Sexta-feira'],
            ['value' => 'saturday', 'label' => 'Sábado'],
            ['value' => 'sunday', 'label' => 'Domingo'],
        ];
    }
}
