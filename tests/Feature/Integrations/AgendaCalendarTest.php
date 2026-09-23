<?php

use App\Models\Integration;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function googleAgendaIntegration(Team $team, array $overrides = []): Integration
{
    return Integration::factory()->for($team)->create(array_merge([
        'type' => 'agenda',
        'provider' => 'google',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour()->timestamp,
            'email' => 'agenda@example.com',
        ],
    ], $overrides));
}

it('lists Google Calendars and releases one with default rules', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://*:5173/*' => Http::response([]),
        'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [
                ['id' => 'clinic@example.com', 'summary' => 'Clínica', 'description' => 'Consultas'],
            ],
        ]),
    ]);
    $user = User::factory()->withTeam()->create();
    $integration = googleAgendaIntegration($user->company());

    $this->actingAs($user)->get(route('integrations.show', 'agenda'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('googleCalendars.0.id', 'clinic@example.com')
            ->where('schedulableCalendars', [])
        );

    $this->actingAs($user)->post(route('integrations.agenda.calendars.store'), [
        'google_calendar_id' => 'clinic@example.com',
    ])->assertRedirect(route('integrations.show', 'agenda'));

    $calendar = SchedulableCalendar::query()->firstOrFail();
    expect($calendar->integration_id)->toBe($integration->id)
        ->and($calendar->display_name)->toBe('Clínica')
        ->and($calendar->duration_minutes)->toBe(30)
        ->and($calendar->minimum_notice_minutes)->toBe(120);
});

it('edits a schedulable Calendar and restores the company window', function () {
    $user = User::factory()->withTeam()->create();
    $integration = googleAgendaIntegration($user->company());
    $calendar = SchedulableCalendar::factory()->for($user->company())->for($integration)->create();

    $this->actingAs($user)->patch(route('integrations.agenda.calendars.update', $calendar), [
        'display_name' => 'Dra. Ana',
        'display_description' => 'Dermatologia',
        'duration_minutes' => 40,
        'minimum_notice_minutes' => 180,
        'business_hours' => ['tuesday' => ['enabled' => true, 'open' => '10:00', 'close' => '16:00']],
    ])->assertRedirect(route('integrations.show', 'agenda'));

    expect($calendar->fresh()->display_name)->toBe('Dra. Ana')
        ->and($calendar->fresh()->duration_minutes)->toBe(40)
        ->and($calendar->fresh()->business_hours['tuesday']['open'])->toBe('10:00');

    $this->actingAs($user)->patch(route('integrations.agenda.calendars.update', $calendar), [
        'display_name' => 'Dra. Ana',
        'display_description' => null,
        'duration_minutes' => 40,
        'minimum_notice_minutes' => 180,
        'business_hours' => null,
    ])->assertRedirect(route('integrations.show', 'agenda'));

    expect($calendar->fresh()->business_hours)->toBeNull();
});

it('marks the Agenda with a reconnect message after invalid_grant', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://*:5173/*' => Http::response([]),
        'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);
    $user = User::factory()->withTeam()->create();
    $integration = googleAgendaIntegration($user->company(), [
        'credentials' => [
            'access_token' => 'expired',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->subMinute()->timestamp,
        ],
    ]);

    $this->actingAs($user)->get(route('integrations.show', 'agenda'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('agendaError', 'Reconecte a conta Google'));

    expect($integration->fresh()->status)->toBe('error')
        ->and($integration->fresh()->status_message)->toBe('Reconecte a conta Google');
});

it('does not expose a Calendar from another company', function () {
    $user = User::factory()->withTeam()->create();
    $other = User::factory()->withTeam()->create();
    $integration = googleAgendaIntegration($other->company());
    $calendar = SchedulableCalendar::factory()->for($other->company())->for($integration)->create();

    $this->actingAs($user)->patch(route('integrations.agenda.calendars.update', $calendar), [
        'display_name' => 'Tentativa',
        'duration_minutes' => 30,
        'minimum_notice_minutes' => 120,
    ])->assertNotFound();
});
