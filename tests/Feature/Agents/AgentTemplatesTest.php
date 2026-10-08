<?php

use App\Ai\Agents\FillTemplateVariables;
use App\Ai\Agents\WhatsAppAgent;
use App\Ai\Tools\SendWhatsAppTemplate;
use App\Consent\ContactConsentService;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTemplateUsage;
use App\Enums\AgentTone;
use App\Enums\ChannelType;
use App\Enums\ConsentPurpose;
use App\Enums\MessageDirection;
use App\Inbox\AgentTemplateSender;
use App\Jobs\RespondToWhatsAppMessage;
use App\Models\Agent;
use App\Models\AgentConversationState;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\SchedulableCalendar;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * @return array{user: User, agent: Agent, channel: Channel, account: WhatsAppAccount, contact: Contact, template: WhatsAppTemplate}
 */
function agentTemplateSetup(AgentTemplateUsage $usage = AgentTemplateUsage::ResumeConversation, array $templateAttributes = []): array
{
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->forceFill(['timezone' => 'America/Sao_Paulo', 'business_hours' => null])->save();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create([
        'type' => ChannelType::WhatsApp,
        'agent_id' => $agent->getKey(),
    ]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create(['access_token' => 'agent-template-token']);
    WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create([
        'phone_number_id' => 'agent-template-phone',
    ]);
    $contact = Contact::factory()->for($team)->create(['name' => 'Ana Souza']);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'name' => 'retomar_conversa',
        ...$templateAttributes,
    ]);
    $agent->whatsappTemplates()->attach($template, ['usage' => $usage->value]);

    return compact('user', 'agent', 'channel', 'account', 'contact', 'template');
}

function agentTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lia',
        'objective' => AgentObjective::Support->value,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá!',
        'templates_configured' => true,
    ], $overrides);
}

function fakeMetaTemplateMessages(): void
{
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'agent-template-message']]])]);
}

function sentTemplateParameters(): array
{
    $parameters = [];
    Http::assertSent(function (Request $request) use (&$parameters): bool {
        if (($request->data()['type'] ?? null) !== 'template') {
            return false;
        }

        $parameters = collect($request->data()['template']['components'])
            ->flatMap(fn (array $component): array => array_column($component['parameters'], 'text'))
            ->all();

        return true;
    });

    return $parameters;
}

it('saves which approved templates the agent may use and for what', function () {
    ['user' => $user, 'agent' => $agent, 'account' => $account] = agentTemplateSetup();
    $reminder = WhatsAppTemplate::factory()->for($account, 'account')->create(['name' => 'lembrete_compromisso']);

    $this->actingAs($user)->patch(route('agents.update', $agent), agentTemplatePayload([
        'template_links' => [['template_id' => $reminder->id, 'usage' => 'appointment_reminder']],
        'reminder_lead_hours' => 6,
    ]))->assertRedirect(route('agents.edit', $agent));

    expect($agent->fresh()->whatsappTemplates->map(fn ($template): array => [$template->name, $template->pivot->usage])->all())
        ->toBe([['lembrete_compromisso', 'appointment_reminder']])
        ->and($agent->fresh()->reminder_lead_hours)->toBe(6);

    $this->actingAs($user)->get(route('agents.edit', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agent.templateLinks.0.usage', 'appointment_reminder')
            ->where('agent.reminderLeadHours', 6)
            ->where('whatsappTemplates.0.usable', true));
});

it('refuses unapproved templates, other companies templates, and two automatic templates for the same use', function () {
    ['user' => $user, 'agent' => $agent, 'account' => $account, 'template' => $template] = agentTemplateSetup();
    $draft = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create();
    $foreign = WhatsAppTemplate::factory()->create();
    $secondResume = WhatsAppTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)->patch(route('agents.update', $agent), agentTemplatePayload([
        'template_links' => [
            ['template_id' => $draft->id, 'usage' => 'on_demand'],
            ['template_id' => $foreign->id, 'usage' => 'on_demand'],
        ],
    ]))->assertSessionHasErrors(['template_links.0.template_id', 'template_links.1.template_id']);

    $this->actingAs($user)->patch(route('agents.update', $agent), agentTemplatePayload([
        'template_links' => [
            ['template_id' => $template->id, 'usage' => 'resume_conversation'],
            ['template_id' => $secondResume->id, 'usage' => 'resume_conversation'],
        ],
    ]))->assertSessionHasErrors('template_links');

    expect($agent->fresh()->whatsappTemplates)->toHaveCount(1);
});

it('resumes the conversation with a template filled by AI when the 24 hour window closed', function () {
    ['channel' => $channel, 'contact' => $contact] = agentTemplateSetup();
    $this->travelTo(now());
    $incoming = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Quero saber do meu pedido',
        'created_at' => now()->subHours(25),
    ]);
    WhatsAppAgent::fake(['Seu pedido saiu para entrega.']);
    FillTemplateVariables::fake([['can_fill' => true, 'values' => ['Ana']]])->preventStrayPrompts();
    fakeMetaTemplateMessages();

    RespondToWhatsAppMessage::dispatchSync($channel->getKey(), $contact->getKey());

    expect(sentTemplateParameters())->toBe(['Ana'])
        ->and($incoming->fresh()->is_responded)->toBeTrue();
    $this->assertDatabaseHas('messages', ['provider_message_id' => 'agent-template-message', 'payload_type' => 'template']);
    $this->assertDatabaseMissing('messages', ['failure_reason' => 'customer_care_window_closed']);
});

it('records why a marketing template was not sent when the contact did not consent', function () {
    ['agent' => $agent, 'channel' => $channel, 'contact' => $contact] = agentTemplateSetup(
        AgentTemplateUsage::FollowUp,
        ['requested_category' => 'marketing', 'assigned_category' => 'marketing'],
    );
    Http::preventStrayRequests();
    FillTemplateVariables::fake()->preventStrayPrompts();

    $delivery = app(AgentTemplateSender::class)->send($agent, $channel, $contact, AgentTemplateUsage::FollowUp, 'teste');

    expect($delivery->sent)->toBeFalse()->and($delivery->reason)->toBe('template_consent_missing');
    FillTemplateVariables::assertNeverPrompted();
    $this->assertDatabaseHas('messages', ['contact_id' => $contact->id, 'failure_reason' => 'template_consent_missing']);
    Http::assertNothingSent();

    app(ContactConsentService::class)->grant($contact, ConsentPurpose::Marketing, 'test');
    FillTemplateVariables::fake([['can_fill' => true, 'values' => ['Ana']]]);
    fakeMetaTemplateMessages();

    expect(app(AgentTemplateSender::class)->send($agent, $channel, $contact, AgentTemplateUsage::FollowUp, 'teste')->sent)->toBeTrue();
});

it('does not send a template when the AI cannot fill every variable', function () {
    ['agent' => $agent, 'channel' => $channel, 'contact' => $contact] = agentTemplateSetup();
    Http::preventStrayRequests();
    FillTemplateVariables::fake([['can_fill' => false, 'values' => ['']]])->preventStrayPrompts();

    $delivery = app(AgentTemplateSender::class)->send($agent, $channel, $contact, AgentTemplateUsage::ResumeConversation, 'teste');

    expect($delivery->reason)->toBe('template_variables_unfilled');
    $this->assertDatabaseHas('messages', ['contact_id' => $contact->id, 'failure_reason' => 'template_variables_unfilled']);
    Http::assertNothingSent();
});

it('reminds booked appointments once when they enter the configured lead time', function () {
    ['agent' => $agent, 'channel' => $channel, 'contact' => $contact] = agentTemplateSetup(AgentTemplateUsage::AppointmentReminder);
    $this->travelTo('2026-10-07 12:00:00 UTC');
    $agent->update(['reminder_lead_hours' => 24]);
    $calendar = SchedulableCalendar::factory()->for($agent->team)->create();
    $agent->schedulableCalendars()->attach($calendar);
    Message::factory()->for($contact)->for($channel)->incoming()->create(['created_at' => now()->subDay()]);
    $soon = Appointment::factory()->for($agent->team)->for($contact)->for($calendar, 'schedulableCalendar')->create([
        'starts_at' => '2026-10-08 09:00:00', 'ends_at' => '2026-10-08 09:30:00', 'status' => 'booked',
    ]);
    $later = Appointment::factory()->for($agent->team)->for($contact)->for($calendar, 'schedulableCalendar')->create([
        'starts_at' => '2026-10-10 09:00:00', 'ends_at' => '2026-10-10 09:30:00', 'status' => 'booked',
    ]);
    $canceled = Appointment::factory()->for($agent->team)->for($contact)->for($calendar, 'schedulableCalendar')->create([
        'starts_at' => '2026-10-08 10:00:00', 'ends_at' => '2026-10-08 10:30:00', 'status' => 'canceled',
    ]);
    FillTemplateVariables::fake([['can_fill' => true, 'values' => ['Ana']]])->preventStrayPrompts();
    fakeMetaTemplateMessages();

    $this->artisan('agents:send-appointment-reminders')->assertSuccessful();
    $this->artisan('agents:send-appointment-reminders')->assertSuccessful();

    Http::assertSentCount(1);
    FillTemplateVariables::assertPrompted(fn ($prompt): bool => $prompt->contains('08/10/2026 às 06:00'));
    expect($soon->fresh()->reminder_sent_at)->not->toBeNull()
        ->and($later->fresh()->reminder_sent_at)->toBeNull()
        ->and($canceled->fresh()->reminder_sent_at)->toBeNull();
});

it('follows up once on a stalled conversation and never on paused or answered ones', function () {
    ['agent' => $agent, 'channel' => $channel, 'contact' => $contact] = agentTemplateSetup(AgentTemplateUsage::FollowUp);
    $this->travelTo('2026-10-07 12:00:00 UTC');
    $agent->update(['follow_up_after_hours' => 24]);
    $agentMessage = Message::factory()->for($contact)->for($channel)->create([
        'direction' => MessageDirection::Outgoing,
        'payload_type' => 'text',
        'content' => 'Quer que eu reserve o plano anual?',
        'created_at' => now()->subHours(30),
    ]);
    $answered = Contact::factory()->for($agent->team)->create();
    Message::factory()->for($answered)->for($channel)->create([
        'direction' => MessageDirection::Outgoing, 'payload_type' => 'text', 'created_at' => now()->subHours(30),
    ]);
    Message::factory()->for($answered)->for($channel)->incoming()->create(['created_at' => now()->subHours(29)]);
    $paused = Contact::factory()->for($agent->team)->create();
    Message::factory()->for($paused)->for($channel)->create([
        'direction' => MessageDirection::Outgoing, 'payload_type' => 'text', 'created_at' => now()->subHours(30),
    ]);
    AgentConversationState::factory()->for($agent->team)->for($paused)->for($channel)->create(['is_paused' => true]);
    FillTemplateVariables::fake([['can_fill' => true, 'values' => ['Ana']]])->preventStrayPrompts();
    fakeMetaTemplateMessages();

    $this->artisan('agents:send-follow-ups')->assertSuccessful();
    $this->artisan('agents:send-follow-ups')->assertSuccessful();

    Http::assertSentCount(1);
    expect(AgentConversationState::withoutGlobalScopes()
        ->where('contact_id', $contact->id)
        ->value('follow_up_message_id'))->toBe($agentMessage->id);
});

it('lets the agent send an on-demand template with the values it chose', function () {
    ['agent' => $agent, 'channel' => $channel, 'contact' => $contact, 'template' => $template] = agentTemplateSetup(AgentTemplateUsage::OnDemand);
    fakeMetaTemplateMessages();
    $tool = new SendWhatsAppTemplate($agent, $channel, $contact, new Collection([$template]), app(AgentTemplateSender::class));

    $rejected = json_decode((string) $tool->handle(new ToolRequest(['template' => $template->name, 'values' => ["Ana\nSouza"]])), true);
    $sent = json_decode((string) $tool->handle(new ToolRequest(['template' => $template->name, 'values' => ['Ana']])), true);

    expect($rejected['ok'])->toBeFalse()
        ->and($sent)->toBe(['ok' => true, 'template' => $template->name])
        ->and(sentTemplateParameters())->toBe(['Ana'])
        ->and($tool->description())->toContain($template->name);
});
