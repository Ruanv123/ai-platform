<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\WhatsAppConnectionMethod;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppConnectionAttempt;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function configureMetaSignup(): void
{
    config()->set('services.whatsapp.platform.app_id', '123456789012345');
    config()->set('services.whatsapp.platform.app_secret', 'platform-app-secret');
    config()->set('services.whatsapp.platform.config_id', '998877665544332');
    config()->set('services.whatsapp.graph_api_url', 'https://graph.facebook.com');
    config()->set('services.whatsapp.graph_api_version', 'v23.0');
}

function fakeMetaSignupRequests(
    ?array $failedPath = null,
    bool $multiplePhoneNumbers = false,
    bool $messagingGrantTargetsWaba = false,
    bool $messagingGrantTargetsSecondPhone = false,
): void {
    Http::preventStrayRequests();
    $failedPaths = [];
    Http::fake(function (Request $request) use (
        $failedPath,
        $multiplePhoneNumbers,
        $messagingGrantTargetsWaba,
        $messagingGrantTargetsSecondPhone,
        &$failedPaths,
    ) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $phoneIds = match (true) {
            $messagingGrantTargetsWaba => ['102030405060708'],
            $messagingGrantTargetsSecondPhone => ['15551234567891'],
            $multiplePhoneNumbers => [],
            default => ['15551234567890'],
        };
        $phoneNumbers = [['id' => '15551234567890']];

        if ($multiplePhoneNumbers) {
            $phoneNumbers[] = ['id' => '15551234567891'];
        }

        if ($failedPath !== null && $path === $failedPath['path'] && ! isset($failedPaths[$path])) {
            $failedPaths[$path] = true;

            return Http::response(['error' => $failedPath['error']], $failedPath['status']);
        }

        return match (true) {
            str_ends_with($path, '/oauth/access_token') => Http::response(['access_token' => 'business-token']),
            str_ends_with($path, '/debug_token') => Http::response([
                'data' => [
                    'app_id' => '123456789012345',
                    'is_valid' => true,
                    'scopes' => [
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                    'granular_scopes' => [
                        [
                            'scope' => 'whatsapp_business_management',
                            'target_ids' => ['102030405060708'],
                        ],
                        [
                            'scope' => 'whatsapp_business_messaging',
                            'target_ids' => $phoneIds,
                        ],
                    ],
                ],
            ]),
            str_ends_with($path, '/102030405060708/phone_numbers') => Http::response([
                'data' => $phoneNumbers,
            ]),
            str_ends_with($path, '/102030405060708/subscribed_apps') => Http::response(['success' => true]),
            str_ends_with($path, '/register') => Http::response(['success' => true]),
            in_array($path, ['/v23.0/15551234567890', '/v23.0/15551234567891'], true) => Http::response([
                'display_phone_number' => '+55 11 99999-8888',
                'verified_name' => 'Empresa Teste',
                'quality_rating' => 'GREEN',
                'name_status' => 'APPROVED',
                'messaging_limit_tier' => 'TIER_1K',
            ]),
            str_ends_with($path, '/102030405060708/message_templates') => Http::response(['data' => []]),
            default => Http::response(['error' => ['code' => 100]], 400),
        };
    });
}

it('completes embedded signup on the server and records each Meta step', function () {
    configureMetaSignup();
    fakeMetaSignupRequests();
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('channels.whatsapp.connect-with-meta'), [
        'code' => 'one-time-meta-code',
    ]);

    $response->assertRedirect(route('channels.whatsapp.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp conectado à Meta e Templates sincronizados.',
        ]);

    $channel = Channel::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $account = WhatsAppAccount::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $phoneNumber = WhatsAppPhoneNumber::query()->where('channel_id', $channel->getKey())->firstOrFail();
    $requests = Http::recorded()->map(fn (array $pair): Request => $pair[0])->all();

    expect($channel->status)->toBe(ChannelStatus::Connected)
        ->and($channel->last_error)->toBeNull()
        ->and($account->connection_method)->toBe(WhatsAppConnectionMethod::TechProvider)
        ->and($account->waba_id)->toBe('102030405060708')
        ->and($account->access_token)->toBe('business-token')
        ->and($account->app_id)->toBeNull()
        ->and($account->app_secret)->toBeNull()
        ->and($account->verify_token)->toBeNull()
        ->and($account->subscribed_at)->not->toBeNull()
        ->and($account->templates_synced_at)->not->toBeNull()
        ->and($phoneNumber->phone_number_id)->toBe('15551234567890')
        ->and($phoneNumber->registration_pin)->toMatch('/^\d{6}$/')
        ->and($phoneNumber->registered_at)->not->toBeNull()
        ->and($phoneNumber->details_synced_at)->not->toBeNull()
        ->and($phoneNumber->registration_pin_required)->toBeFalse()
        ->and($phoneNumber->display_phone_number)->toBe('+55 11 99999-8888')
        ->and($phoneNumber->verified_name)->toBe('Empresa Teste')
        ->and($phoneNumber->quality_rating)->toBe('GREEN')
        ->and($phoneNumber->name_status)->toBe('APPROVED')
        ->and($phoneNumber->messaging_limit_tier)->toBe('TIER_1K');
    $this->assertDatabaseCount('whatsapp_connection_attempts', 0);

    expect(DB::table('whatsapp_phone_numbers')
        ->where('id', $phoneNumber->getKey())
        ->value('registration_pin'))
        ->not->toBe($phoneNumber->registration_pin);

    expect(array_map(
        fn (Request $request): string => (string) parse_url($request->url(), PHP_URL_PATH),
        $requests,
    ))->toBe([
        '/v23.0/oauth/access_token',
        '/v23.0/debug_token',
        '/v23.0/102030405060708/phone_numbers',
        '/v23.0/102030405060708/subscribed_apps',
        '/v23.0/15551234567890/register',
        '/v23.0/15551234567890',
        '/v23.0/102030405060708/message_templates',
    ]);
    expect(array_map(fn (Request $request): string => $request->method(), $requests))
        ->toBe(['GET', 'GET', 'GET', 'POST', 'POST', 'GET', 'GET']);

    expect($requests[0]->data())->toMatchArray([
        'client_id' => '123456789012345',
        'client_secret' => 'platform-app-secret',
        'code' => 'one-time-meta-code',
    ])->and($requests[1]->hasHeader('Authorization', 'Bearer 123456789012345|platform-app-secret'))->toBeTrue()
        ->and($requests[4]->data())->toMatchArray([
            'messaging_product' => 'whatsapp',
            'pin' => $phoneNumber->registration_pin,
        ]);
});

it('rejects WABA and phone IDs sent by the browser', function () {
    configureMetaSignup();
    fakeMetaSignupRequests();
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('channels.whatsapp.connect-with-meta'), [
        'code' => 'one-time-meta-code',
        'waba_id' => 'browser-waba-id',
        'phone_number_id' => 'browser-phone-id',
    ]);

    $response->assertSessionHasErrors(['waba_id', 'phone_number_id']);
    $this->assertDatabaseCount('channels', 0);
    $this->assertDatabaseCount('whatsapp_accounts', 0);
    Http::assertNothingSent();
});

it('fails when a multi-number WABA does not identify the selected phone in token grants', function () {
    configureMetaSignup();
    fakeMetaSignupRequests(multiplePhoneNumbers: true, messagingGrantTargetsWaba: true);
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('channels.whatsapp.connect-with-meta'), [
        'code' => 'one-time-meta-code',
    ]);

    $response->assertRedirect(route('channels.whatsapp.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Não foi possível identificar com segurança o número autorizado pela Meta.',
        ]);
    $this->assertDatabaseCount('channels', 1);
    $this->assertDatabaseCount('whatsapp_accounts', 0);
    $this->assertDatabaseCount('whatsapp_connection_attempts', 1);
});

it('persists encrypted initial credentials and resumes only the failed Meta step', function (string $failedPath) {
    configureMetaSignup();
    fakeMetaSignupRequests([
        'path' => $failedPath,
        'status' => 400,
        'error' => ['code' => 10],
    ]);
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'), ['code' => 'one-time-meta-code'])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O token não tem permissão para acessar este número.',
        ]);

    $channel = Channel::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $attempt = WhatsAppConnectionAttempt::query()->where('channel_id', $channel->getKey())->firstOrFail();
    $rawAttempt = DB::table('whatsapp_connection_attempts')->where('id', $attempt->getKey())->firstOrFail();

    expect($channel->status)->toBe(ChannelStatus::Error)
        ->and($channel->last_error)->toBe('O token não tem permissão para acessar este número.')
        ->and(DB::table('whatsapp_connection_attempts')->count())->toBe(1);

    if ($failedPath === '/v23.0/oauth/access_token') {
        expect($rawAttempt->authorization_code)->not->toBe('one-time-meta-code')
            ->and($attempt->authorization_code)->toBe('one-time-meta-code')
            ->and($attempt->access_token)->toBeNull();
    } else {
        expect($rawAttempt->access_token)->toBe('business-token')
            ->and($attempt->authorization_code)->toBeNull()
            ->and($attempt->access_token)->toBe('business-token');
    }

    $this->actingAs($user)
        ->get(route('channels.whatsapp.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp.method', 'tech_provider')
            ->where('whatsapp.lastError', 'O token não tem permissão para acessar este número.')
        );

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp conectado à Meta e Templates sincronizados.',
        ]);

    $attemptCounts = Http::recorded()
        ->map(fn (array $pair): string => (string) parse_url($pair[0]->url(), PHP_URL_PATH))
        ->countBy()
        ->all();

    expect($attemptCounts['/v23.0/oauth/access_token'])->toBe(
        $failedPath === '/v23.0/oauth/access_token' ? 2 : 1,
    )->and($attemptCounts['/v23.0/debug_token'])->toBe(
        $failedPath === '/v23.0/debug_token' ? 2 : 1,
    )->and($attemptCounts['/v23.0/102030405060708/phone_numbers'])->toBe(
        $failedPath === '/v23.0/102030405060708/phone_numbers' ? 2 : 1,
    );

    $this->assertDatabaseCount('whatsapp_connection_attempts', 0);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected);
})->with([
    'code exchange' => ['/v23.0/oauth/access_token'],
    'token validation' => ['/v23.0/debug_token'],
    'WABA number discovery' => ['/v23.0/102030405060708/phone_numbers'],
]);

it('does not reuse an App próprio channel for Tech Provider signup', function () {
    configureMetaSignup();
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create();
    WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'), ['code' => 'new-code'])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Esta Empresa já possui um Canal oficial do WhatsApp conectado.',
        ]);

    expect($account->fresh()->connection_method)->toBe(WhatsAppConnectionMethod::OwnApp);
    Http::assertNothingSent();
    $this->assertDatabaseCount('channels', 1);
    $this->assertDatabaseCount('whatsapp_connection_attempts', 0);
});

it('registers the second number selected by the messaging grant', function () {
    configureMetaSignup();
    fakeMetaSignupRequests(multiplePhoneNumbers: true, messagingGrantTargetsSecondPhone: true);
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('channels.whatsapp.connect-with-meta'), [
        'code' => 'one-time-meta-code',
    ]);

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $phoneNumber = WhatsAppPhoneNumber::query()->firstOrFail();
    $registerRequest = Http::recorded()
        ->map(fn (array $pair): Request => $pair[0])
        ->first(fn (Request $request): bool => str_ends_with(
            (string) parse_url($request->url(), PHP_URL_PATH),
            '/register',
        ));

    expect($phoneNumber->phone_number_id)->toBe('15551234567891')
        ->and($phoneNumber->registered_at)->not->toBeNull()
        ->and($registerRequest)->toBeInstanceOf(Request::class)
        ->and(parse_url($registerRequest->url(), PHP_URL_PATH))->toBe('/v23.0/15551234567891/register')
        ->and($registerRequest->data()['pin'])->toBe($phoneNumber->registration_pin);
});

it('assigns an agent from the company after connecting with Meta', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $otherTeamAgent = Agent::factory()->create();

    $this->actingAs($user)->patch(route('channels.whatsapp.update-agent', $channel), [
        'agent_id' => $agent->getKey(),
    ])->assertRedirect(route('channels.whatsapp.edit'));

    expect($channel->fresh()->agent_id)->toBe($agent->getKey());

    $this->actingAs($user)->patch(route('channels.whatsapp.update-agent', $channel), [
        'agent_id' => $otherTeamAgent->getKey(),
    ])->assertSessionHasErrors('agent_id');

    expect($channel->fresh()->agent_id)->toBe($agent->getKey());
});

it('stores a failed Meta step on the channel and preserves completed markers', function () {
    configureMetaSignup();
    fakeMetaSignupRequests([
        'path' => '/v23.0/15551234567890/register',
        'status' => 400,
        'error' => ['code' => 190],
    ]);
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('channels.whatsapp.connect-with-meta'), [
        'code' => 'one-time-meta-code',
    ]);

    $response->assertRedirect(route('channels.whatsapp.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O access token é inválido ou expirou.',
        ]);

    $channel = Channel::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $account = WhatsAppAccount::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $phoneNumber = WhatsAppPhoneNumber::query()->where('channel_id', $channel->getKey())->firstOrFail();

    expect($channel->status)->toBe(ChannelStatus::Error)
        ->and($channel->last_error)->toBe('O access token é inválido ou expirou.')
        ->and($account->subscribed_at)->not->toBeNull()
        ->and($phoneNumber->registered_at)->toBeNull()
        ->and($phoneNumber->registration_pin)->toMatch('/^\d{6}$/');
});

it('resumes from the first incomplete Meta step without repeating completed calls', function (string $failedPath) {
    configureMetaSignup();
    Http::preventStrayRequests();
    $attempts = [];
    Http::fake(function (Request $request) use (&$attempts, $failedPath) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $attempts[$path] = ($attempts[$path] ?? 0) + 1;

        if ($path === $failedPath && $attempts[$path] === 1) {
            return Http::response(['error' => ['code' => 10]], 400);
        }

        return match ($path) {
            '/v23.0/oauth/access_token' => Http::response(['access_token' => 'business-token']),
            '/v23.0/debug_token' => Http::response([
                'data' => [
                    'app_id' => '123456789012345',
                    'is_valid' => true,
                    'scopes' => ['whatsapp_business_management', 'whatsapp_business_messaging'],
                    'granular_scopes' => [
                        ['scope' => 'whatsapp_business_management', 'target_ids' => ['102030405060708']],
                        ['scope' => 'whatsapp_business_messaging', 'target_ids' => ['15551234567890']],
                    ],
                ],
            ]),
            '/v23.0/102030405060708/phone_numbers' => Http::response([
                'data' => [['id' => '15551234567890']],
            ]),
            '/v23.0/102030405060708/subscribed_apps' => Http::response(['success' => true]),
            '/v23.0/15551234567890/register' => Http::response(['success' => true]),
            '/v23.0/15551234567890' => Http::response([
                'display_phone_number' => '+55 11 99999-8888',
                'verified_name' => 'Empresa Teste',
                'quality_rating' => 'GREEN',
                'name_status' => 'APPROVED',
                'messaging_limit_tier' => 'TIER_1K',
            ]),
            '/v23.0/102030405060708/message_templates' => Http::response(['data' => []]),
            default => Http::response(['error' => ['code' => 100]], 400),
        };
    });
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'), ['code' => 'one-time-meta-code'])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O token não tem permissão para acessar este número.',
        ]);

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp conectado à Meta e Templates sincronizados.',
        ]);

    $account = WhatsAppAccount::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $phoneNumber = WhatsAppPhoneNumber::query()->whereHas('channel', fn ($query) => $query->where('team_id', $user->company()->getKey()))->firstOrFail();

    expect($account->subscribed_at)->not->toBeNull()
        ->and($account->templates_synced_at)->not->toBeNull()
        ->and($phoneNumber->registered_at)->not->toBeNull()
        ->and($phoneNumber->details_synced_at)->not->toBeNull()
        ->and($attempts['/v23.0/102030405060708/subscribed_apps'])->toBe(
            $failedPath === '/v23.0/102030405060708/subscribed_apps' ? 2 : 1,
        )
        ->and($attempts['/v23.0/15551234567890/register'])->toBe(
            $failedPath === '/v23.0/15551234567890/register' ? 2 : 1,
        )
        ->and($attempts['/v23.0/15551234567890'])->toBe(
            $failedPath === '/v23.0/15551234567890' ? 2 : 1,
        )
        ->and($attempts['/v23.0/102030405060708/message_templates'])->toBe(
            $failedPath === '/v23.0/102030405060708/message_templates' ? 2 : 1,
        )
        ->and($attempts['/v23.0/oauth/access_token'])->toBe(1)
        ->and($attempts['/v23.0/debug_token'])->toBe(1)
        ->and($attempts['/v23.0/102030405060708/phone_numbers'])->toBe(1);
})->with([
    'WABA subscription' => ['/v23.0/102030405060708/subscribed_apps'],
    'phone registration' => ['/v23.0/15551234567890/register'],
    'phone details' => ['/v23.0/15551234567890'],
    'templates' => ['/v23.0/102030405060708/message_templates'],
]);

it('requires new Meta authorization instead of resuming a fully completed connection', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WhatsApp,
        'status' => ChannelStatus::Error,
        'last_error' => 'O access token é inválido ou expirou.',
    ]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->techProvider()->create([
        'subscribed_at' => now(),
        'templates_synced_at' => now(),
    ]);
    WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create([
            'registered_at' => now(),
            'details_synced_at' => now(),
        ]);
    Http::preventStrayRequests();

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Esta conexão já concluiu todas as etapas. Autorize novamente com a Meta para atualizar o token.',
        ]);

    expect($channel->fresh()->status)->toBe(ChannelStatus::Error);
    Http::assertNothingSent();
});

it('asks for an existing two-step PIN and allows retrying an incorrect PIN', function () {
    configureMetaSignup();
    Http::preventStrayRequests();
    $registerAttempts = 0;
    Http::fake(function (Request $request) use (&$registerAttempts) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/v23.0/15551234567890/register') {
            $registerAttempts++;

            if ($registerAttempts < 3) {
                return Http::response(['error' => ['code' => 133005]], 400);
            }

            return Http::response(['success' => true]);
        }

        return match ($path) {
            '/v23.0/oauth/access_token' => Http::response(['access_token' => 'business-token']),
            '/v23.0/debug_token' => Http::response([
                'data' => [
                    'app_id' => '123456789012345',
                    'is_valid' => true,
                    'scopes' => ['whatsapp_business_management', 'whatsapp_business_messaging'],
                    'granular_scopes' => [
                        ['scope' => 'whatsapp_business_management', 'target_ids' => ['102030405060708']],
                        ['scope' => 'whatsapp_business_messaging', 'target_ids' => ['15551234567890']],
                    ],
                ],
            ]),
            '/v23.0/102030405060708/phone_numbers' => Http::response([
                'data' => [['id' => '15551234567890']],
            ]),
            '/v23.0/102030405060708/subscribed_apps' => Http::response(['success' => true]),
            '/v23.0/15551234567890' => Http::response([
                'display_phone_number' => '+55 11 99999-8888',
                'verified_name' => 'Empresa Teste',
                'quality_rating' => 'GREEN',
                'name_status' => 'APPROVED',
                'messaging_limit_tier' => 'TIER_1K',
            ]),
            '/v23.0/102030405060708/message_templates' => Http::response(['data' => []]),
            default => Http::response(['error' => ['code' => 100]], 400),
        };
    });
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'), ['code' => 'one-time-meta-code'])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Este número já possui um PIN de confirmação em duas etapas. Informe os seis dígitos para continuar.',
        ]);

    $channel = Channel::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $phoneNumber = WhatsAppPhoneNumber::query()->where('channel_id', $channel->getKey())->firstOrFail();
    expect($phoneNumber->registration_pin_required)->toBeTrue();

    $this->actingAs($user)->get(route('channels.whatsapp.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp.registrationPinRequired', true),
        );

    $pinRetry = $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'), ['pin' => '111111'])
        ->assertRedirect(route('channels.whatsapp.edit'));
    $pinRetry->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('channels.whatsapp.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp.lastError', 'O PIN informado está incorreto. Confira os seis dígitos e tente novamente.')
            ->where('whatsapp.registrationPinRequired', true),
        );

    expect($registerAttempts)->toBe(2)
        ->and($phoneNumber->fresh()->registration_pin_required)->toBeTrue()
        ->and($phoneNumber->fresh()->registration_pin)->toBe('111111');

    $this->actingAs($user)
        ->post(route('channels.whatsapp.connect-with-meta'), ['pin' => '123456'])
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp conectado à Meta e Templates sincronizados.',
        ]);

    $phoneNumber = $phoneNumber->fresh();
    $account = WhatsAppAccount::query()->where('team_id', $user->company()->getKey())->firstOrFail();

    expect($registerAttempts)->toBe(3)
        ->and($phoneNumber->registration_pin_required)->toBeFalse()
        ->and($phoneNumber->registration_pin)->toBe('123456')
        ->and(DB::table('whatsapp_phone_numbers')->where('id', $phoneNumber->getKey())->value('registration_pin'))
        ->not->toBe('123456')
        ->and($account->subscribed_at)->not->toBeNull()
        ->and($account->templates_synced_at)->not->toBeNull();
});

it('disconnects a Tech Provider WABA without deregistering the phone and keeps history', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->connected()->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->techProvider()->create([
        'waba_id' => '102030405060708',
        'access_token' => 'business-token',
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create(['registration_pin' => '123456']);
    $contact = Contact::factory()->for($team)->create();
    $message = Message::factory()->for($contact)->for($channel)->create([
        'content' => 'Histórico preservado.',
    ]);
    config()->set('services.whatsapp.graph_api_url', 'https://graph.facebook.com');
    config()->set('services.whatsapp.graph_api_version', 'v23.0');
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

    $this->actingAs($user)
        ->delete(route('channels.whatsapp.destroy', $channel))
        ->assertRedirect(route('channels.whatsapp.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp desconectado. Seus Contatos e Mensagens foram mantidos.',
        ]);

    $this->assertDatabaseMissing('channels', ['id' => $channel->getKey()]);
    $this->assertDatabaseMissing('whatsapp_accounts', ['id' => $account->getKey()]);
    $this->assertDatabaseMissing('whatsapp_phone_numbers', ['id' => $phoneNumber->getKey()]);
    $this->assertDatabaseHas('contacts', ['id' => $contact->getKey()]);
    $this->assertDatabaseHas('messages', ['id' => $message->getKey(), 'channel_id' => null]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && parse_url($request->url(), PHP_URL_PATH) === '/v23.0/102030405060708/subscribed_apps');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/deregister'));
});

it('disconnects an own App without calling Meta and keeps messages', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->connected()->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create();
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create();
    $contact = Contact::factory()->for($team)->create();
    $message = Message::factory()->for($contact)->for($channel)->create();
    Http::preventStrayRequests();

    $this->actingAs($user)
        ->delete(route('channels.whatsapp.destroy', $channel))
        ->assertRedirect(route('channels.whatsapp.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp desconectado. Seus Contatos e Mensagens foram mantidos.',
        ]);

    $this->assertDatabaseMissing('channels', ['id' => $channel->getKey()]);
    $this->assertDatabaseMissing('whatsapp_accounts', ['id' => $account->getKey()]);
    $this->assertDatabaseMissing('whatsapp_phone_numbers', ['id' => $phoneNumber->getKey()]);
    $this->assertDatabaseHas('contacts', ['id' => $contact->getKey()]);
    $this->assertDatabaseHas('messages', ['id' => $message->getKey(), 'channel_id' => null]);
    Http::assertNothingSent();
});

it('removes local connection records when Meta cannot cancel the webhook subscription', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->connected()->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->techProvider()->create([
        'access_token' => 'business-token',
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create();
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190]], 400)]);

    $this->actingAs($user)
        ->delete(route('channels.whatsapp.destroy', $channel))
        ->assertRedirect(route('channels.whatsapp.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O WhatsApp foi removido desta plataforma, mas a Meta não confirmou o cancelamento dos webhooks: O access token é inválido ou expirou.',
        ]);

    $this->assertDatabaseMissing('channels', ['id' => $channel->getKey()]);
    $this->assertDatabaseMissing('whatsapp_accounts', ['id' => $account->getKey()]);
    $this->assertDatabaseMissing('whatsapp_phone_numbers', ['id' => $phoneNumber->getKey()]);
    Http::assertSentCount(1);
});
