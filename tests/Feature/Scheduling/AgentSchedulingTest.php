<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Tools\BookAppointment;
use App\Ai\Tools\CancelAppointment;
use App\Ai\Tools\ConsultAvailableTimes;
use App\Ai\Tools\ListAppointments;
use App\Ai\Tools\RescheduleAppointment;
use App\Knowledge\AgentInstructionTemplate;
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

it('checks a requested late slot before limiting the availability results', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['clinic@example.com' => ['busy' => []]]]),
    ]);
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $calendar = schedulableCalendarForTest($team, [
        'business_hours' => ['monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00']],
    ]);

    $result = (new ConsultAvailableTimes($team, new Collection([$calendar]), new SchedulingService))->handle(new Request([
        'start_date' => '2026-02-02',
        'end_date' => '2026-02-02',
        'preferred_time' => '16:00',
        'limit' => 3,
    ]));

    expect(json_decode((string) $result, true)['horarios'])->toHaveCount(1)
        ->and(json_decode((string) $result, true)['horarios'][0]['inicio'])->toBe('2026-02-02T16:00:00-03:00');
    Http::assertSentCount(1);
});

it('returns no availability when the requested exact slot is occupied', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['clinic@example.com' => ['busy' => [
            ['start' => '2026-02-02T16:00:00-03:00', 'end' => '2026-02-02T16:30:00-03:00'],
        ]]]]),
    ]);
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $calendar = schedulableCalendarForTest($team, [
        'business_hours' => ['monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00']],
    ]);

    $result = (new ConsultAvailableTimes($team, new Collection([$calendar]), new SchedulingService))->handle(new Request([
        'start_date' => '2026-02-02', 'end_date' => '2026-02-02', 'preferred_time' => '16:00',
    ]));

    expect(json_decode((string) $result, true)['horarios'])->toBe([]);
    Http::assertSentCount(1);
});

it('rejects an invalid exact time before querying Google', function () {
    Http::preventStrayRequests();
    $team = Team::factory()->create();
    $calendar = schedulableCalendarForTest($team);

    $result = (new ConsultAvailableTimes($team, new Collection([$calendar]), new SchedulingService))->handle(new Request([
        'preferred_time' => '25:00',
    ]));

    expect(json_decode((string) $result, true)['ok'])->toBeFalse();
    Http::assertNothingSent();
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
    expect($appointment->google_event_id)->toBe('google-event-1')
        ->and($appointment->fresh()->starts_at->toIso8601String())->toBe('2026-02-02T12:00:00+00:00')
        ->and($appointment->fresh()->starts_at->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('09:00');
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
    $this->travelTo('2026-02-02 11:00:00 UTC');
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
        ->and($payload['compromissos'][0]['inicio'])->toBe('2026-02-04T11:00:00-03:00')
        ->and($first->fresh()->starts_at->toIso8601String())->toBe($moved->toIso8601String())
        ->and($second->fresh()->status)->toBe('cancelled');
});

it('remarques the existing Google event and persists the new time in UTC', function () {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $calendar = schedulableCalendarForTest($team, [
        'business_hours' => ['monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00']],
    ]);
    $contact = Contact::factory()->for($team)->create();
    $appointment = Appointment::factory()->for($team)->for($contact)->for($calendar, 'schedulableCalendar')->create([
        'google_event_id' => 'existing-event',
        'starts_at' => '2026-02-02 16:00:00',
        'ends_at' => '2026-02-02 16:30:00',
    ]);
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/clinic%40example.com/events/existing-event' => Http::response([
            'id' => 'existing-event', 'etag' => 'event-version', 'status' => 'confirmed',
            'start' => ['dateTime' => '2026-02-02T13:00:00-03:00'],
            'end' => ['dateTime' => '2026-02-02T13:30:00-03:00'],
        ]),
        'https://www.googleapis.com/calendar/v3/calendars/clinic%40example.com/events?*' => Http::response(['items' => [
            ['id' => 'existing-event'],
            ['id' => 'transparent-event', 'transparency' => 'transparent'],
        ]]),
    ]);

    $result = (new RescheduleAppointment($contact, new Collection([$calendar]), new SchedulingService))->handle(new Request([
        'appointment_id' => $appointment->id,
        'start' => '2026-02-02T16:00:00-03:00',
    ]));

    expect(json_decode((string) $result, true)['ok'])->toBeTrue();
    expect($appointment->fresh()->starts_at->toIso8601String())->toBe('2026-02-02T19:00:00+00:00');
    $this->assertDatabaseCount('appointments', 1);
    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && $request->data()['start']['dateTime'] === '2026-02-02T16:00:00-03:00'
        && $request->hasHeader('If-Match', 'event-version'));
    Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['DELETE', 'POST']));
});

it('preserves the original appointment when the new slot conflicts or Google rejects the update', function (bool $occupied) {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $calendar = schedulableCalendarForTest($team, [
        'business_hours' => ['monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00']],
    ]);
    $contact = Contact::factory()->for($team)->create();
    $appointment = Appointment::factory()->for($team)->for($contact)->for($calendar, 'schedulableCalendar')->create([
        'google_event_id' => 'existing-event', 'starts_at' => '2026-02-02 16:00:00', 'ends_at' => '2026-02-02 16:30:00',
    ]);
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/clinic%40example.com/events/existing-event' => fn ($request) => $request->method() === 'PATCH'
            ? Http::response(['error' => ['message' => 'Version conflict']], 412)
            : Http::response(['id' => 'existing-event', 'start' => ['dateTime' => '2026-02-02T13:00:00-03:00']]),
        'https://www.googleapis.com/calendar/v3/calendars/clinic%40example.com/events?*' => Http::response(['items' => $occupied ? [['id' => 'another-event']] : []]),
    ]);

    $result = (new RescheduleAppointment($contact, new Collection([$calendar]), new SchedulingService))->handle(new Request([
        'appointment_id' => $appointment->id, 'start' => '2026-02-02T16:00:00-03:00',
    ]));

    expect(json_decode((string) $result, true)['ok'])->toBeFalse();
    expect($appointment->fresh()->starts_at->toIso8601String())->toBe('2026-02-02T16:00:00+00:00');
    Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['DELETE', 'POST']));
})->with([true, false]);

it('rejects rescheduling for another contact or a calendar not allowed to the Agent', function (bool $differentContact) {
    $this->travelTo('2026-02-02 11:00:00 UTC');
    Http::preventStrayRequests();
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $calendar = schedulableCalendarForTest($team);
    $owner = Contact::factory()->for($team)->create();
    $caller = $differentContact ? Contact::factory()->for($team)->create() : $owner;
    $appointment = Appointment::factory()->for($team)->for($owner, 'contact')->for($calendar, 'schedulableCalendar')->create();

    $result = (new RescheduleAppointment($caller, new Collection($differentContact ? [$calendar] : []), new SchedulingService))->handle(new Request([
        'appointment_id' => $appointment->id, 'start' => '2026-02-02T10:00:00-03:00',
    ]));

    expect(json_decode((string) $result, true)['ok'])->toBeFalse();
    Http::assertNothingSent();
})->with([true, false]);

it('gives the Agent current local time and a complete scheduling workflow', function () {
    $this->travelTo('2026-10-05 18:30:00 UTC');
    $team = Team::factory()->create([
        'timezone' => 'America/Sao_Paulo',
    ]);
    $calendar = schedulableCalendarForTest($team, [
        'display_name' => 'Degustações Grão Dourado',
        'display_description' => 'Atendimento presencial na cafeteria',
        'duration_minutes' => 30,
    ]);
    $agent = Agent::factory()->for($team)->create();
    $agent->schedulableCalendars()->attach($calendar);

    $instructions = app(AgentInstructionTemplate::class)->render(
        $agent->load('schedulableCalendars'),
    );

    expect($instructions)
        ->toContain('05/10/2026 às 15:30')
        ->toContain('Você conhece a data e o ano atuais: nunca diga que não tem acesso a eles')
        ->toContain('Quando o Contato informar dia e mês sem ano, use a próxima ocorrência futura coerente com a conversa')
        ->toContain('America/Sao_Paulo')
        ->toContain('Degustações Grão Dourado')
        ->toContain('Atendimento presencial na cafeteria')
        ->toContain('30 minutos')
        ->toContain('consultar_horarios')
        ->toContain('marcar_compromisso')
        ->toContain('confirmação explícita')
        ->toContain('Só diga que o Compromisso foi marcado depois que a ferramenta confirmar sucesso');
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
