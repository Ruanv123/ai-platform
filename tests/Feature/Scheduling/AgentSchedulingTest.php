<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Tools\BookAppointment;
use App\Ai\Tools\CancelAppointment;
use App\Ai\Tools\ConsultAvailableTimes;
use App\Ai\Tools\ListAppointments;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use App\Models\User;
use App\Scheduling\SchedulingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;

function schedulableCalendarForTest(Team $team, array $overrides = []): SchedulableCalendar
{
    $integration = Integration::factory()->for($team)->create([
        'type' => 'agenda',
        'provider' => 'google',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ]);

    return SchedulableCalendar::factory()->for($team)->for($integration)->create(array_merge([
        'google_calendar_id' => 'clinic@example.com',
        'display_name' => 'Dra. Ana',
        'display_description' => 'Dermatologia',
        'duration_minutes' => 30,
        'minimum_notice_minutes' => 0,
        'business_hours' => [
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '12:00'],
        ],
    ], $overrides));
}

it('returns only real slots in the calendar window and labels the calendar', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => [
                'clinic@example.com' => [
                    'busy' => [['start' => '2026-02-02T09:30:00-03:00', 'end' => '2026-02-02T10:00:00-03:00']],
                ],
            ],
        ]),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company()->forceFill(['timezone' => 'America/Sao_Paulo']);
    $team->save();
    $calendar = schedulableCalendarForTest($team);

    $result = (new ConsultAvailableTimes($team, new Collection([$calendar]), new SchedulingService))
        ->handle(new Request([
            'calendar_id' => $calendar->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-02',
            'limit' => 10,
        ]));
    $payload = json_decode((string) $result, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['ok'])->toBeTrue()
        ->and(collect($payload['horarios'])->pluck('inicio')->all())->toBe([
            '2026-02-02T09:00:00-03:00',
            '2026-02-02T10:00:00-03:00',
            '2026-02-02T10:30:00-03:00',
            '2026-02-02T11:00:00-03:00',
            '2026-02-02T11:30:00-03:00',
        ])
        ->and($payload['horarios'][0]['calendario'])->toContain('Dra. Ana');
});

it('rechecks the Google occupancy before creating an appointment', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => ['clinic@example.com' => ['busy' => [['start' => '2026-02-02T09:00:00-03:00', 'end' => '2026-02-02T09:30:00-03:00']]]],
        ]),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company()->forceFill(['timezone' => 'America/Sao_Paulo']);
    $team->save();
    $calendar = schedulableCalendarForTest($team);
    $contact = Contact::factory()->for($team)->named('João')->create();

    $result = (new BookAppointment($contact, new Collection([$calendar]), new SchedulingService))
        ->handle(new Request([
            'calendar_id' => $calendar->id,
            'start' => '2026-02-02T09:00:00-03:00',
            'reason' => 'Consulta',
        ]));
    $payload = json_decode((string) $result, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['motivo'])->toContain('ocupado');
    $this->assertDatabaseCount('appointments', 0);
});

it('creates the Google event and platform appointment with contact trace data', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['clinic@example.com' => ['busy' => []]]]),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'google-event-1']),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company()->forceFill(['timezone' => 'America/Sao_Paulo']);
    $team->save();
    $calendar = schedulableCalendarForTest($team);
    $contact = Contact::factory()->for($team)->named('João')->create(['phone' => '5511999999999']);

    $result = (new BookAppointment($contact, new Collection([$calendar]), new SchedulingService))
        ->handle(new Request([
            'calendar_id' => $calendar->id,
            'start' => '2026-02-02T09:00:00-03:00',
            'reason' => 'Retorno',
        ]));
    $payload = json_decode((string) $result, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['ok'])->toBeTrue();
    $appointment = Appointment::query()->firstOrFail();
    expect($appointment->google_event_id)->toBe('google-event-1');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/clinic%40example.com/events'
        && $request->data()['summary'] === 'João (via WhatsApp)'
        && str_contains($request->data()['description'], '5511999999999')
        && $request->data()['extendedProperties']['private']['contact_id'] === (string) $contact->id
        && ! isset($request->data()['attendees']));
});

it('simulates Playground mutations without Google writes or appointments', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $calendar = schedulableCalendarForTest($user->company());

    $result = (new BookAppointment(null, new Collection([$calendar]), new SchedulingService, true))
        ->handle(new Request([
            'calendar_id' => $calendar->id,
            'start' => '2026-02-02T09:00:00-03:00',
            'reason' => 'Teste',
        ]));

    $payload = json_decode((string) $result, true, flags: JSON_THROW_ON_ERROR);
    expect($payload['ok'])->toBeTrue()->and($payload['simulado'])->toBeTrue();
    $this->assertDatabaseCount('appointments', 0);
});

it('reconciles moved and deleted Google events when listing appointments', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $calendar = schedulableCalendarForTest($team);
    $contact = Contact::factory()->for($team)->create();
    $moved = now()->addDays(2)->setTime(14, 0);
    $first = Appointment::factory()->create([
        'team_id' => $team->id,
        'schedulable_calendar_id' => $calendar->id,
        'contact_id' => $contact->id,
        'google_event_id' => 'moved-event',
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(10, 30),
    ]);
    $second = Appointment::factory()->create([
        'team_id' => $team->id,
        'schedulable_calendar_id' => $calendar->id,
        'contact_id' => $contact->id,
        'google_event_id' => 'deleted-event',
        'starts_at' => now()->addDay()->setTime(11, 0),
        'ends_at' => now()->addDay()->setTime(11, 30),
    ]);
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/*/events/moved-event' => Http::response([
            'status' => 'confirmed',
            'start' => ['dateTime' => $moved->toIso8601String()],
            'end' => ['dateTime' => $moved->addMinutes(30)->toIso8601String()],
        ]),
        'https://www.googleapis.com/calendar/v3/calendars/*/events/deleted-event' => Http::response([], 404),
    ]);

    $result = (new ListAppointments($contact, new SchedulingService))->handle(new Request([]));
    $payload = json_decode((string) $result, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['compromissos'])->toHaveCount(1)
        ->and($payload['compromissos'][0]['inicio'])->toBe($moved->toIso8601String())
        ->and($first->fresh()->starts_at->toIso8601String())->toBe($moved->toIso8601String())
        ->and($second->fresh()->status)->toBe('cancelled');
});

it('runs the availability Tool in the Playground with a real Google query', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['clinic@example.com' => ['busy' => []]]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company()->forceFill([
        'timezone' => 'America/Sao_Paulo',
        'business_hours' => ['monday' => ['enabled' => true, 'open' => '09:00', 'close' => '10:00']],
    ]);
    $team->save();
    $calendar = schedulableCalendarForTest($team);
    $calendar->update(['business_hours' => null, 'minimum_notice_minutes' => 0]);
    $agent = Agent::factory()->for($team)->create();
    $agent->schedulableCalendars()->attach($calendar);
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'consultar_horarios', [
            'calendar_id' => $calendar->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-02',
            'limit' => 1,
        ]),
        'Há um horário disponível.',
    ]);

    $response = $this->actingAs($user)->postJson(route('agents.playground.stream'), [
        'name' => $agent->name,
        'objective' => $agent->objective->value,
        'tone' => $agent->tone->value,
        'uses_emojis' => false,
        'response_length' => $agent->response_length->value,
        'welcome_message' => $agent->welcome_message,
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [$calendar->id],
        'history' => [],
        'message' => 'Quais horários existem?',
    ]);

    expect($response->streamedContent())->toContain('09:00');
});

it('rejects cancellation of another contact and tolerates an already deleted Google event', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $calendar = schedulableCalendarForTest($team);
    $owner = Contact::factory()->for($team)->create();
    $other = Contact::factory()->for($team)->create();
    $appointment = Appointment::factory()->create([
        'team_id' => $team->id,
        'schedulable_calendar_id' => $calendar->id,
        'contact_id' => $owner->id,
        'google_event_id' => 'missing-event',
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(10, 30),
    ]);

    $forbidden = json_decode((string) (new CancelAppointment($other, new SchedulingService))->handle(new Request([
        'appointment_id' => $appointment->id,
    ])), true, flags: JSON_THROW_ON_ERROR);
    expect($forbidden['ok'])->toBeFalse();

    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/*/events/missing-event' => Http::response([], 404),
    ]);
    $cancelled = json_decode((string) (new CancelAppointment($owner, new SchedulingService))->handle(new Request([
        'appointment_id' => $appointment->id,
    ])), true, flags: JSON_THROW_ON_ERROR);

    expect($cancelled['ok'])->toBeTrue()->and($appointment->fresh()->status)->toBe('cancelled');
});
