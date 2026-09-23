<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Integrations\GoogleCalendarClient;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class AgendaCalendarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $team = $this->authorizeManagement($request);
        $validated = $request->validate([
            'google_calendar_id' => ['required', 'string', 'max:512'],
        ]);
        $integration = $team->integrations()->where('type', 'agenda')->firstOrFail();

        try {
            $googleCalendar = collect((new GoogleCalendarClient($integration))->calendars())
                ->first(fn (array $calendar): bool => (string) ($calendar['id'] ?? '') === $validated['google_calendar_id']);
            if ($googleCalendar === null) {
                abort(404);
            }

            $calendar = $integration->schedulableCalendars()->firstOrCreate(
                ['google_calendar_id' => $validated['google_calendar_id']],
                [
                    'team_id' => $team->getKey(),
                    'display_name' => (string) ($googleCalendar['summary'] ?? $googleCalendar['id']),
                    'display_description' => $googleCalendar['description'] ?? null,
                    'duration_minutes' => 30,
                    'minimum_notice_minutes' => 120,
                ],
            );
            $calendar->update([
                'display_name' => $calendar->display_name ?: (string) ($googleCalendar['summary'] ?? $googleCalendar['id']),
            ]);
        } catch (Throwable $exception) {
            return to_route('integrations.show', 'agenda')->with('agenda_error', $exception->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calendário liberado para agendamento.']);

        return to_route('integrations.show', 'agenda');
    }

    public function update(Request $request, SchedulableCalendar $schedulableCalendar): RedirectResponse
    {
        $team = $this->authorizeManagement($request);
        abort_unless($schedulableCalendar->team_id === $team->getKey(), 404);
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'display_description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'minimum_notice_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*' => ['array:enabled,open,close'],
            'business_hours.*.enabled' => ['sometimes', 'boolean'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
        ]);
        if (isset($validated['business_hours'])) {
            foreach ($validated['business_hours'] as $day) {
                if (($day['enabled'] ?? true) && filled($day['open'] ?? null) && filled($day['close'] ?? null) && $day['open'] >= $day['close']) {
                    return back()->withErrors(['business_hours' => 'A abertura deve ser anterior ao fechamento.']);
                }
            }
        }

        $schedulableCalendar->update($validated);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Regras do Calendário salvas.']);

        return to_route('integrations.show', 'agenda');
    }

    public function destroy(Request $request, SchedulableCalendar $schedulableCalendar): RedirectResponse
    {
        $team = $this->authorizeManagement($request);
        abort_unless($schedulableCalendar->team_id === $team->getKey(), 404);
        $schedulableCalendar->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calendário retirado do agendamento.']);

        return to_route('integrations.show', 'agenda');
    }

    private function authorizeManagement(Request $request): Team
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);

        return $team;
    }
}
