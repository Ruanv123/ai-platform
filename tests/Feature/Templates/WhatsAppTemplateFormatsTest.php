<?php

use App\Enums\ChannelType;
use App\Enums\WhatsAppTemplateFormat;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function templateFormatsAccount(User $user): WhatsAppAccount
{
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create([
        'app_id' => 'formats-app-id',
        'access_token' => 'formats-access-token',
    ]);
    WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();

    return $account;
}

function templateFormatsSubmittedPayload(): array
{
    $payload = null;
    Http::assertSent(function (Request $request) use (&$payload): bool {
        if (str_ends_with($request->url(), '/message_templates') && $request->method() === 'POST') {
            $payload = $request->data();

            return true;
        }

        return false;
    });

    return $payload;
}

it('creates a media card carousel and submits every card to Meta', function () {
    Storage::fake('whatsapp_media');
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['id' => 'upload:card-0'])
            ->push(['h' => 'card-0-handle'])
            ->push(['id' => 'upload:card-1'])
            ->push(['h' => 'card-1-handle'])
            ->push(['id' => 'meta-carousel', 'status' => 'PENDING', 'category' => 'MARKETING']),
    ]);
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);
    $cardButtons = fn (int $card): array => [
        ['type' => 'url', 'text' => 'Ver produto', 'url' => 'https://example.com/produto/'.$card],
        ['type' => 'quick_reply', 'text' => 'Tenho interesse'],
    ];

    $response = $this->actingAs($user)->post(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'colecao_verao',
        'language' => 'pt_BR',
        'requested_category' => 'marketing',
        'format' => 'carousel',
        'submit' => true,
        'body' => 'Olá {{1}}, veja a nova coleção.',
        'variables' => [['position' => 1, 'component' => 'body', 'example' => 'Maria']],
        'card_header_format' => 'IMAGE',
        'cards' => [
            ['body' => 'Peças leves.', 'media' => UploadedFile::fake()->create('card-0.png', 1, 'image/png'), 'buttons' => $cardButtons(0)],
            ['body' => 'Acessórios.', 'media' => UploadedFile::fake()->create('card-1.png', 1, 'image/png'), 'buttons' => $cardButtons(1)],
        ],
    ]);

    $template = WhatsAppTemplate::query()->where('name', 'colecao_verao')->firstOrFail();
    $response->assertRedirect(route('templates.show', $template));
    expect($template->format)->toBe(WhatsAppTemplateFormat::Carousel)
        ->and($template->status->value)->toBe('pending')
        ->and($template->carouselCards())->toHaveCount(2);
    expect(templateFormatsSubmittedPayload()['components'])->toBe([
        [
            'type' => 'BODY',
            'text' => 'Olá {{1}}, veja a nova coleção.',
            'example' => ['body_text' => [['Maria']]],
        ],
        [
            'type' => 'CAROUSEL',
            'cards' => collect([0, 1])
                ->map(fn (int $card): array => [
                    'components' => [
                        ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['card-'.$card.'-handle']]],
                        ['type' => 'BODY', 'text' => $card === 0 ? 'Peças leves.' : 'Acessórios.'],
                        ['type' => 'BUTTONS', 'buttons' => [
                            ['type' => 'URL', 'text' => 'Ver produto', 'url' => 'https://example.com/produto/'.$card],
                            ['type' => 'QUICK_REPLY', 'text' => 'Tenho interesse'],
                        ]],
                    ],
                ])
                ->all(),
        ],
    ]);
});

it('keeps the saved media of each carousel card even after reordering', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create([
        'requested_category' => 'marketing',
        'format' => WhatsAppTemplateFormat::Carousel,
    ]);
    foreach ([0, 1] as $card) {
        $template->components()->create([
            'card_index' => $card,
            'type' => 'header',
            'format' => 'image',
            'media_handle' => 'existing-card-'.$card,
            'position' => 0,
        ]);
    }
    $buttons = [['type' => 'quick_reply', 'text' => 'Quero']];

    $this->actingAs($user)->put(route('templates.update', $template), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => $template->name,
        'language' => 'pt_BR',
        'requested_category' => 'marketing',
        'format' => 'carousel',
        'body' => 'Novidades da semana.',
        'card_header_format' => 'IMAGE',
        'cards' => [
            ['existing_index' => 1, 'buttons' => $buttons],
            ['existing_index' => 0, 'buttons' => $buttons],
        ],
    ])->assertRedirect(route('templates.show', $template));

    $headers = collect($template->fresh()->carouselCards())->map(fn (array $card): ?string => $card['header']->media_handle);
    expect($headers->all())->toBe(['existing-card-1', 'existing-card-0']);
    Http::assertNothingSent();
});

it('requires media for every carousel card and a carousel-compatible category', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);

    $this->actingAs($user)->postJson(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'carrossel_utilidade',
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'format' => 'carousel',
        'body' => 'Confira.',
        'card_header_format' => 'IMAGE',
        'cards' => [
            ['buttons' => [['type' => 'quick_reply', 'text' => 'Quero']]],
            ['buttons' => [['type' => 'quick_reply', 'text' => 'Quero']]],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'format' => 'O formato escolhido não está disponível para esta categoria.',
        'cards.0.media' => 'Envie a mídia de cada card.',
        'cards.1.media' => 'Envie a mídia de cada card.',
    ]);

    expect(WhatsAppTemplate::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('submits a limited-time offer with its offer component, copy code, and URL buttons', function () {
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-offer', 'status' => 'PENDING', 'category' => 'MARKETING'])]);
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);

    $this->actingAs($user)->post(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'oferta_relampago',
        'language' => 'pt_BR',
        'requested_category' => 'marketing',
        'format' => 'limited_time_offer',
        'submit' => true,
        'header_type' => 'NONE',
        'offer_text' => 'Só hoje!',
        'offer_has_expiration' => true,
        'body' => 'Aproveite a oferta.',
        'buttons' => [
            ['type' => 'copy_code', 'example' => 'RELAMPAGO'],
            ['type' => 'url', 'text' => 'Ver oferta', 'url' => 'https://example.com/oferta'],
        ],
    ])->assertRedirect();

    expect(templateFormatsSubmittedPayload()['components'])->toBe([
        ['type' => 'LIMITED_TIME_OFFER', 'limited_time_offer' => ['text' => 'Só hoje!', 'has_expiration' => true]],
        ['type' => 'BODY', 'text' => 'Aproveite a oferta.'],
        ['type' => 'BUTTONS', 'buttons' => [
            ['type' => 'COPY_CODE', 'example' => 'RELAMPAGO'],
            ['type' => 'URL', 'text' => 'Ver oferta', 'url' => 'https://example.com/oferta'],
        ]],
    ]);
});

it('submits an authentication template with preset text, expiration, and one-tap autofill', function () {
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-auth', 'status' => 'PENDING', 'category' => 'AUTHENTICATION'])]);
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);

    $this->actingAs($user)->post(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'codigo_verificacao',
        'language' => 'pt_BR',
        'requested_category' => 'authentication',
        'format' => 'authentication',
        'submit' => true,
        'otp_type' => 'one_tap',
        'package_name' => 'com.empresa.app',
        'signature_hash' => 'K8a/AINcGX7',
        'add_security_recommendation' => true,
        'code_expiration_minutes' => 10,
    ])->assertRedirect();

    expect(templateFormatsSubmittedPayload()['components'])->toBe([
        ['type' => 'BODY', 'add_security_recommendation' => true],
        ['type' => 'FOOTER', 'code_expiration_minutes' => 10],
        ['type' => 'BUTTONS', 'buttons' => [[
            'type' => 'OTP',
            'text' => 'Copiar código',
            'otp_type' => 'one_tap',
            'autofill_text' => 'Preencher',
            'supported_apps' => [['package_name' => 'com.empresa.app', 'signature_hash' => 'K8a/AINcGX7']],
        ]]],
    ]);
});

it('requires the Android app for autofill authentication codes', function () {
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);

    $this->actingAs($user)->postJson(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'codigo_sem_app',
        'language' => 'pt_BR',
        'requested_category' => 'authentication',
        'format' => 'authentication',
        'otp_type' => 'zero_tap',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'package_name' => 'Informe o pacote do app Android.',
        'signature_hash' => 'Informe o hash da assinatura do app Android.',
    ]);
});

it('submits a call permission request with only its body', function () {
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'meta-call', 'status' => 'PENDING', 'category' => 'UTILITY'])]);
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);

    $this->actingAs($user)->post(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'permissao_ligacao',
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'format' => 'call_permission',
        'submit' => true,
        'body' => 'Podemos ligar para você?',
    ])->assertRedirect();

    expect(templateFormatsSubmittedPayload()['components'])->toBe([
        ['type' => 'BODY', 'text' => 'Podemos ligar para você?'],
        ['type' => 'CALL_PERMISSION_REQUEST'],
    ]);
});

it('keeps a coupon as draft when Meta would reject its buttons', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);

    $response = $this->actingAs($user)->post(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'cupom_com_link',
        'language' => 'pt_BR',
        'requested_category' => 'marketing',
        'format' => 'coupon',
        'submit' => true,
        'body' => 'Use o cupom.',
        'buttons' => [
            ['type' => 'copy_code', 'example' => 'BEMVINDO10'],
            ['type' => 'url', 'text' => 'Abrir', 'url' => 'https://example.com'],
        ],
    ]);

    $template = WhatsAppTemplate::query()->where('name', 'cupom_com_link')->firstOrFail();
    $response->assertRedirect(route('templates.show', $template));
    $response->assertInertiaFlash('toast.type', 'error');
    expect($template->status->value)->toBe('draft');
    Http::assertNothingSent();
});

it('imports Meta templates with the format their components describe', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
        [
            'id' => 'remote-carousel',
            'name' => 'colecao_remota',
            'language' => 'pt_BR',
            'status' => 'APPROVED',
            'category' => 'MARKETING',
            'components' => [
                ['type' => 'BODY', 'text' => 'Veja.'],
                ['type' => 'CAROUSEL', 'cards' => [
                    ['components' => [
                        ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['remote-card-0']]],
                        ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Quero']]],
                    ]],
                    ['components' => [
                        ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['remote-card-1']]],
                        ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Quero']]],
                    ]],
                ]],
            ],
        ],
        [
            'id' => 'remote-auth',
            'name' => 'codigo_remoto',
            'language' => 'pt_BR',
            'status' => 'APPROVED',
            'category' => 'AUTHENTICATION',
            'components' => [
                ['type' => 'BODY', 'text' => '*{{1}}* é seu código de verificação.', 'add_security_recommendation' => true],
                ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'Copiar código', 'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE&code=otp{{1}}']]],
            ],
        ],
    ]])]);

    $this->actingAs($user)->post(route('templates.sync'))->assertRedirect(route('templates.index'));

    $carousel = WhatsAppTemplate::query()->where('name', 'colecao_remota')->firstOrFail();
    expect($carousel->format)->toBe(WhatsAppTemplateFormat::Carousel)
        ->and(collect($carousel->carouselCards())->map(fn (array $card): ?string => $card['header']->media_handle)->all())
        ->toBe(['remote-card-0', 'remote-card-1'])
        ->and(WhatsAppTemplate::query()->where('name', 'codigo_remoto')->firstOrFail()->format)
        ->toBe(WhatsAppTemplateFormat::Authentication);
});

it('filters templates by format and opens the editor with the saved cards', function () {
    $user = User::factory()->withTeam()->create();
    $account = templateFormatsAccount($user);
    $carousel = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create([
        'requested_category' => 'marketing',
        'format' => WhatsAppTemplateFormat::Carousel,
    ]);
    $carousel->components()->create([
        'card_index' => 0,
        'type' => 'header',
        'format' => 'video',
        'media_handle' => 'video-card-handle',
        'position' => 0,
    ]);
    WhatsAppTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)->get(route('templates.index', ['format' => 'carousel']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('templates.data.0.id', $carousel->id)
            ->where('templates.data.0.formatLabel', 'Carrossel de mídia')
            ->missing('templates.data.1'));

    $this->actingAs($user)->get(route('templates.edit', $carousel))
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/form')
            ->where('template.format', 'carousel')
            ->where('template.cards.0.headerFormat', 'VIDEO')
            ->where('template.cards.0.mediaAvailable', true));

    $this->actingAs($user)->get(route('templates.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/form')
            ->where('template', null)
            ->where('accounts.0.id', $account->id));
});
