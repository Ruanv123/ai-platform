<?php

use App\Enums\TeamRole;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\SchedulableCalendar;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('isolates integrations between companies', function () {
    $owner = User::factory()->withTeam()->create();
    $other = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($owner->company())->create();

    $this->actingAs($other)->deleteJson(route('integrations.destroy', $integration))->assertNotFound();
});

it('redirects the integration manager to Google with calendar scopes and offline consent', function () {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => 'http://localhost/integrations/agenda/google/callback',
    ]);
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.agenda.connect'));

    $response->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['access_type'])->toBe('offline')
        ->and($query['prompt'])->toBe('consent')
        ->and(urldecode($query['scope']))->toContain('calendar.events')
        ->and(urldecode($query['scope']))->toContain('calendar.freebusy')
        ->and(urldecode($query['scope']))->toContain('calendar.calendarlist.readonly');
});

it('stores a Google Agenda for the company on callback', function () {
    $user = User::factory()->withTeam()->create();
    Socialite::fake('google', SocialiteUser::fake([
        'email' => 'agenda@example.com',
        'token' => 'access-token',
        'refreshToken' => 'refresh-token',
        'expiresIn' => 3600,
    ]));

    $response = $this->actingAs($user)->get(route('integrations.agenda.callback'));

    $response->assertRedirect(route('integrations.show', 'agenda'));
    $integration = Integration::query()->where('team_id', $user->company()->id)->firstOrFail();
    expect($integration->type)->toBe('agenda')
        ->and($integration->provider)->toBe('google')
        ->and($integration->credential('email'))->toBe('agenda@example.com')
        ->and($integration->credential('refresh_token'))->toBe('refresh-token')
        ->and($integration->getRawOriginal('credentials'))->not->toContain('access-token');
});

it('does not save a callback with an invalid state', function () {
    $user = User::factory()->withTeam()->create();
    Socialite::fake('google', SocialiteUser::fake(['email' => 'agenda@example.com']));

    $this->actingAs($user)->get(route('integrations.agenda.callback', ['state' => 'wrong']))
        ->assertRedirect(route('integrations.show', 'agenda'));

    $this->assertDatabaseCount('integrations', 0);
});

it('reconnects the same Google Agenda and clears its error', function () {
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'agenda',
        'provider' => 'google',
        'status' => 'error',
        'status_message' => 'Reconecte a conta Google',
        'credentials' => ['access_token' => 'old', 'refresh_token' => 'old-refresh', 'email' => 'old@example.com'],
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'email' => 'new@example.com',
        'token' => 'new-access',
        'refreshToken' => 'new-refresh',
    ]));

    $this->actingAs($user)->get(route('integrations.agenda.callback'));

    expect($user->company()->integrations()->where('type', 'agenda')->count())->toBe(1)
        ->and($integration->fresh()->status)->toBe('connected')
        ->and($integration->fresh()->credential('email'))->toBe('new@example.com');
});

it('forbids Members from connecting or disconnecting the Agenda', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $integration = Integration::factory()->for($owner->company())->create(['type' => 'agenda', 'provider' => 'google']);

    $this->actingAs($member)->get(route('integrations.agenda.connect'))->assertForbidden();
    $this->actingAs($member)->delete(route('integrations.destroy', $integration))->assertForbidden();
});

it('disconnects the Agenda and keeps appointments as history', function () {
    Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response([], 200)]);
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'agenda',
        'provider' => 'google',
        'credentials' => ['access_token' => 'access', 'refresh_token' => 'refresh'],
    ]);
    $calendar = SchedulableCalendar::factory()->for($user->company())->for($integration)->create();
    $contact = Contact::factory()->for($user->company())->create();
    $appointment = Appointment::factory()->for($user->company())->for($calendar, 'schedulableCalendar')->for($contact)->create();

    $this->actingAs($user)->delete(route('integrations.destroy', $integration))->assertRedirect(route('integrations.index'));

    $this->assertModelMissing($calendar);
    expect($appointment->fresh()->status)->toBe('booked')
        ->and($appointment->fresh()->schedulable_calendar_id)->toBeNull();
});
