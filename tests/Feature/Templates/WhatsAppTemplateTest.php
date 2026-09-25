<?php

use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createTemplateWhatsAppChannel(User $user, array $channelAttributes = []): array
{
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsApp,
        ...$channelAttributes,
    ]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create();
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();

    return [$channel, $phoneNumber, $account];
}

it('redirects guests to login when they visit templates', function () {
    $this->get(route('templates.index'))->assertRedirect(route('login'));
});

it('syncs Meta templates idempotently and marks removed templates', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$account->waba_id.'/message_templates';
    Http::fakeSequence()
        ->push([
            'data' => [[
                'id' => 'meta-123',
                'name' => 'consulta_amanha',
                'language' => 'pt_BR',
                'status' => 'APPROVED',
                'category' => 'UTILITY',
                'quality_score' => ['score' => 'GREEN'],
                'components' => [
                    ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Olá {{1}}', 'example' => ['header_text' => ['Ana']]],
                    ['type' => 'BODY', 'text' => 'Sua consulta é {{1}}.', 'example' => ['body_text' => [['amanhã']]]],
                    ['type' => 'FOOTER', 'text' => 'Até breve'],
                    ['type' => 'BUTTONS', 'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Confirmar'],
                        ['type' => 'URL', 'text' => 'Abrir', 'url' => 'https://example.com/{{1}}', 'example' => ['https://example.com/consulta']],
                        ['type' => 'PHONE_NUMBER', 'text' => 'Ligar', 'phone_number' => '+5511999999999'],
                        ['type' => 'COPY_CODE', 'example' => 'PROMO10'],
                    ]],
                ],
            ]],
        ])
        ->push(['data' => []]);

    $first = $this->actingAs($user)->post(route('templates.sync'));
    $second = $this->actingAs($user)->post(route('templates.sync'));

    $first->assertRedirect(route('templates.index'));
    $second->assertRedirect(route('templates.index'));
    expect(WhatsAppTemplate::query()->where('whatsapp_account_id', $account->id)->count())->toBe(1);
    $template = WhatsAppTemplate::query()->with('components.variables', 'components.buttons')->firstOrFail();
    $variables = $template->variableDefinitions();
    expect($template->status->value)->toBe('deleted')
        ->and($template->quality_rating)->toBe('GREEN')
        ->and($template->bodyText())->toBe('Sua consulta é {{1}}.')
        ->and($template->components->pluck('type')->all())->toBe(['header', 'body', 'footer', 'buttons'])
        ->and($variables)->toContain(['position' => 1, 'component' => 'header', 'example' => 'Ana'])
        ->and($variables)->toContain(['position' => 1, 'component' => 'body', 'example' => 'amanhã'])
        ->and($variables)->toContain(['position' => 1, 'component' => 'button', 'buttonIndex' => 1, 'example' => 'https://example.com/consulta']);
    expect($template->buttonDefinitions())->toContain(['type' => 'COPY_CODE', 'example' => 'PROMO10']);
    expect($template->history()->count())->toBe(2);
    $this->assertDatabaseHas('whatsapp_template_variables', [
        'whatsapp_template_component_id' => $template->components->firstWhere('type', 'body')->id,
        'position' => 1,
        'example' => 'amanhã',
    ]);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), $endpoint));
});

it('renders only templates from the current team and exposes the approval explanation', function () {
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->rejected('INVALID_FORMAT')->create([
        'rejection_explanation' => 'Revise as variáveis antes de reenviar.',
    ]);
    $otherUser = User::factory()->withTeam()->create();
    [, , $otherAccount] = createTemplateWhatsAppChannel($otherUser);
    $otherTemplate = WhatsAppTemplate::factory()->for($otherAccount, 'account')->create();

    $response = $this->actingAs($user)->get(route('templates.index'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('templates/index')
        ->where('templates.data.0.id', $template->id)
        ->where('templates.data.0.status', 'rejected')
        ->missing('templates.data.1')
    );

    $this->actingAs($user)->get(route('templates.show', $template))
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/show')
            ->where('template.rejectionExplanation', 'Revise as variáveis antes de reenviar.')
        );
    $this->actingAs($user)->get(route('templates.show', $otherTemplate))->assertNotFound();
});

it('preserves a draft when Meta submission fails and moves it to pending on success', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create([
        'name' => 'lembrete_consulta',
        'requested_category' => 'utility',
    ]);
    $template->components()->where('type', 'body')->update(['text' => 'Olá {{1}}, seu horário é amanhã.']);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$account->waba_id.'/message_templates';
    Http::fakeSequence('*')
        ->pushFailedConnection()
        ->push(['id' => 'meta-new', 'status' => 'PENDING', 'category' => 'UTILITY']);

    $failed = $this->actingAs($user)->post(route('templates.submit', $template));

    $failed->assertSessionHasErrors('template');
    expect($template->fresh()->status->value)->toBe('draft');

    $submitted = $this->actingAs($user)->post(route('templates.submit', $template));

    $submitted->assertRedirect(route('templates.show', $template));
    expect($template->fresh()->status->value)->toBe('pending')
        ->and($template->fresh()->meta_template_id)->toBe('meta-new');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === $endpoint);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === $endpoint
        && $request->data()['components'] === [[
            'type' => 'BODY',
            'text' => 'Olá {{1}}, seu horário é amanhã.',
            'example' => ['body_text' => [['Ana']]],
        ]]);
});

it('creates normalized components, variables, and every supported button type', function () {
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);

    $response = $this->actingAs($user)->postJson(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'lembrete_consulta',
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'header_text' => 'Olá {{1}}',
        'body' => 'Sua consulta é {{1}}.',
        'variables' => [
            ['position' => 1, 'component' => 'header', 'example' => 'Ana'],
            ['position' => 1, 'component' => 'body', 'example' => 'amanhã'],
        ],
        'buttons' => [
            ['type' => 'quick_reply', 'text' => 'Confirmar'],
            ['type' => 'url', 'text' => 'Abrir', 'url' => 'https://example.com/{{1}}', 'example' => 'https://example.com/consulta'],
            ['type' => 'copy_code', 'example' => 'PROMO10'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('body', 'Sua consulta é {{1}}.')
        ->assertJsonCount(3, 'buttons')
        ->assertJsonPath('buttons.2.type', 'COPY_CODE');

    $template = WhatsAppTemplate::query()->where('name', 'lembrete_consulta')->firstOrFail();
    expect($template->components()->count())->toBe(3)
        ->and($template->components()->where('type', 'buttons')->firstOrFail()->buttons()->pluck('type')->all())
        ->toBe(['quick_reply', 'url', 'copy_code']);
    expect($template->variableDefinitions())->toContain(['position' => 1, 'component' => 'header', 'example' => 'Ana'])
        ->and($template->variableDefinitions())->toContain(['position' => 1, 'component' => 'body', 'example' => 'amanhã'])
        ->and($template->variableDefinitions())->toContain(['position' => 1, 'component' => 'button', 'buttonIndex' => 1, 'example' => 'https://example.com/consulta']);

    $phoneButtonResponse = $this->actingAs($user)->postJson(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'lembrete_telefone',
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'body' => 'Ligue para confirmar.',
        'buttons' => [
            ['type' => 'phone_number', 'text' => 'Ligar', 'phone_number' => '+5511999999999'],
        ],
    ]);

    $phoneButtonResponse->assertCreated()
        ->assertJsonPath('buttons.0.type', 'PHONE_NUMBER');
});

it('sends conversation template parameters according to normalized variables', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [$channel, $phoneNumber, $account] = createTemplateWhatsAppChannel($user);
    $phoneNumber->update(['phone_number_id' => 'template-phone']);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'name' => 'appointment_notice',
    ]);
    $contact = Contact::factory()->for($user->company())->create();
    Http::fakeSequence('graph.facebook.com/*')
        ->push(['messages' => [['id' => 'model-template-message']]])
        ->push(['messages' => [['id' => 'named-template-message']]]);
    $adapter = app(WhatsAppOutputAdapterFactory::class)->make($channel);

    $adapter->sendTemplate($channel, $contact, $template, ['Ana']);
    $adapter->sendTemplate($channel, $contact, $template->name, ['Bia']);

    Http::assertSent(fn (Request $request): bool => $request->data()['template']['components'] === [[
        'type' => 'body',
        'parameters' => [['type' => 'text', 'text' => 'Ana']],
    ]]);
    Http::assertSent(fn (Request $request): bool => $request->data()['template']['components'] === [[
        'type' => 'body',
        'parameters' => [['type' => 'text', 'text' => 'Bia']],
    ]]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'model-template-message',
        'payload_type' => 'template',
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'named-template-message',
        'payload_type' => 'template',
    ]);
});

it('forbids a member from submitting a template', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    [, , $account] = createTemplateWhatsAppChannel($owner);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create();

    $this->actingAs($member)->post(route('templates.submit', $template))->assertForbidden();
});
