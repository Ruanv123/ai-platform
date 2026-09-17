<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateBusinessHoursRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHoursController extends Controller
{
    /**
     * Show the current team's business hours settings.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $team = $user->company();

        abort_if(! $team, 404);

        return Inertia::render('settings/business-hours', [
            'team' => [
                'businessHours' => $team->business_hours,
                'timezone' => $team->timezone,
            ],
            'timezones' => timezone_identifiers_list(),
            'weekdays' => Weekday::options(),
            'canUpdate' => $user->can('update', $team),
        ]);
    }

    /**
     * Update the current team's business hours.
     */
    public function update(UpdateBusinessHoursRequest $request): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);

        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário de atendimento atualizado.']);

        return to_route('business-hours.edit');
    }
}
