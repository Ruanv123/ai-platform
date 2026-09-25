<?php

use App\Enums\ChannelStatus;
use App\Enums\WhatsAppConnectionMethod;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
    Http::fake(function (Request $request) use (
        $failedPath,
        $multiplePhoneNumbers,
        $messagingGrantTargetsWaba,
        $messagingGrantTargetsSecondPhone,
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

        if ($failedPath !== null && $path === $failedPath['path']) {
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
        ->and($phoneNumber->phone_number_id)->toBe('15551234567890')
        ->and($phoneNumber->registration_pin)->toMatch('/^\d{6}$/')
        ->and($phoneNumber->registered_at)->not->toBeNull()
        ->and($phoneNumber->display_phone_number)->toBe('+55 11 99999-8888')
        ->and($phoneNumber->verified_name)->toBe('Empresa Teste')
        ->and($phoneNumber->quality_rating)->toBe('GREEN')
        ->and($phoneNumber->name_status)->toBe('APPROVED')
        ->and($phoneNumber->messaging_limit_tier)->toBe('TIER_1K');

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
    $this->assertDatabaseCount('channels', 0);
    $this->assertDatabaseCount('whatsapp_accounts', 0);
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
