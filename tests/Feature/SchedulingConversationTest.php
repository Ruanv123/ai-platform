<?php

use App\Ai\Agents\WhatsAppAgent;
use App\Enums\ChannelType;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

it('keeps persisted appointment context in later Agent turns without exposing other contacts', function () {
    $this->travelTo('2026-10-05 20:00:00 UTC');
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $agent = Agent::factory()->for($team)->create();
    $calendar = SchedulableCalendar::factory()->for($team)->create();
    $agent->schedulableCalendars()->attach($calendar);
    $contact = Contact::factory()->for($team)->create();
    Appointment::factory()->for($team)->for($contact)->for($calendar, 'schedulableCalendar')->create([
        'starts_at' => '2026-10-06 16:00:00', 'ends_at' => '2026-10-06 16:30:00',
    ]);
    Appointment::factory()->for($team)->for(Contact::factory()->for($team))->for($calendar, 'schedulableCalendar')->create([
        'starts_at' => '2026-10-06 19:00:00', 'ends_at' => '2026-10-06 19:30:00',
    ]);

    $instructions = (new WhatsAppAgent(
        $agent, collect(), app(AgentInstructionTemplate::class), app(KnowledgeBaseSearch::class), contact: $contact,
    ))->instructions();

    expect($instructions)->toContain('06/10/2026 13:00 até 13:30')
        ->toContain('Compromissos já registrados deste Contato')
        ->not->toContain('06/10/2026 16:00 até 16:30');
});

it('executes an exact time query from WhatsApp and audits the tool result without message contents', function () {
    $this->travelTo('2026-10-05 20:00:00 UTC');
    $team = Team::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsApp, 'agent_id' => $agent->id]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create(['app_secret' => 'test-secret']);
    $phone = WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'agenda', 'provider' => 'google', 'status' => 'connected',
        'credentials' => ['access_token' => 'test-token', 'expires_at' => now()->addHour()->timestamp],
    ]);
    $calendar = SchedulableCalendar::factory()->for($team)->for($integration)->create([
        'google_calendar_id' => 'coffee-calendar',
        'duration_minutes' => 30,
        'minimum_notice_minutes' => 0,
        'business_hours' => ['tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00']],
    ]);
    $agent->schedulableCalendars()->attach($calendar);
    $body = json_encode([
        'entry' => [[
            'id' => $account->waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => $phone->phone_number_id],
                    'contacts' => [['wa_id' => '5511999999999', 'profile' => ['name' => 'Ana']]],
                    'messages' => [[
                        'id' => 'appointment-request', 'from' => '5511999999999', 'type' => 'text',
                        'text' => ['body' => 'Amanhã às 16h, por favor.'],
                    ]],
                ],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR);
    WhatsAppAgent::fake([
        new ToolCall((string) Str::uuid7(), 'consultar_horarios', [
            'calendar_id' => $calendar->id, 'start_date' => '2026-10-06', 'end_date' => '2026-10-06', 'preferred_time' => '16:00', 'limit' => 3,
        ]),
        '06/10/2026 às 16h está disponível. Posso confirmar?',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['coffee-calendar' => ['busy' => []]]]),
        'https://graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'outgoing-appointment-request']]]),
    ]);
    Log::spy();

    $this->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
    ], $body)->assertOk();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://www.googleapis.com/calendar/v3/freeBusy');
    Log::shouldHaveReceived('info')->with('Agent scheduling tool completed', Mockery::on(fn (array $context): bool => $context['tool'] === 'consultar_horarios'
        && $context['arguments']['preferred_time'] === '16:00'
        && $context['result']['ok'] === true
        && $context['slot_count'] === 1
        && ! isset($context['arguments']['reason'])
    ))->once();
    $this->assertDatabaseHas('messages', ['content' => '06/10/2026 às 16h está disponível. Posso confirmar?']);
    $this->assertDatabaseCount('appointments', 0);
});
