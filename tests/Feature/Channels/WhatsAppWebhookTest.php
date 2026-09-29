<?php

use App\Enums\ChannelType;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Channel;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

function createWhatsAppWebhookAccount(string $appSecret = 'app-secret'): array
{
    $phoneNumber = WhatsAppPhoneNumber::factory()->create();
    $account = $phoneNumber->whatsappAccount;
    $account->update(['app_secret' => $appSecret]);

    return [$account, $phoneNumber];
}

it('returns the challenge and records verification for a matching public handshake', function () {
    $account = WhatsAppAccount::factory()->create([
        'verify_token' => 'known-verify-token',
    ]);
    $verificationTime = now()->setSeconds(12)->setMicrosecond(0);
    $this->travelTo($verificationTime);

    $response = $this->get(route('webhooks.whatsapp.app-proprio').'?hub.mode=subscribe&hub.verify_token=known-verify-token&hub.challenge=challenge-value');

    $response->assertOk()
        ->assertContent('challenge-value')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($account->fresh()->webhook_verified_at)->toEqual($verificationTime);
});

it('returns 403 without recording verification for an invalid handshake', function (string $query) {
    $account = WhatsAppAccount::factory()->create([
        'verify_token' => 'known-verify-token',
    ]);

    $response = $this->get(route('webhooks.whatsapp.app-proprio').'?'.$query);

    $response->assertForbidden();
    expect($account->fresh()->webhook_verified_at)->toBeNull();
})->with([
    'wrong mode' => 'hub.mode=unsubscribe&hub.verify_token=known-verify-token&hub.challenge=challenge-value',
    'unknown token' => 'hub.mode=subscribe&hub.verify_token=unknown-token&hub.challenge=challenge-value',
    'missing mode' => 'hub.verify_token=known-verify-token&hub.challenge=challenge-value',
    'missing token' => 'hub.mode=subscribe&hub.challenge=challenge-value',
    'missing challenge' => 'hub.mode=subscribe&hub.verify_token=known-verify-token',
]);

function whatsappWebhookBody(string $wabaId, string $phoneNumberId, string $field = 'messages'): string
{
    return json_encode([
        'entry' => [[
            'id' => $wabaId,
            'changes' => [[
                'field' => $field,
                'value' => [
                    'metadata' => [
                        'phone_number_id' => $phoneNumberId,
                    ],
                ],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR);
}

function postOwnAppWhatsAppWebhook(TestCase $testCase, string $body, ?string $signature): TestResponse
{
    return $testCase->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ]), $body);
}

function postTechProviderWhatsAppWebhook(TestCase $testCase, string $body, ?string $signature): TestResponse
{
    return $testCase->call('POST', route('webhooks.whatsapp.tech-provider'), [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ]), $body);
}

it('returns the challenge for a matching platform handshake', function () {
    config(['services.whatsapp.platform.webhook_verify_token' => 'platform-verify-token']);

    $response = $this->get(route('webhooks.whatsapp.tech-provider').'?hub.mode=subscribe&hub.verify_token=platform-verify-token&hub.challenge=platform-challenge');

    $response->assertOk()
        ->assertContent('platform-challenge')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

it('returns 403 when the platform handshake is invalid', function (string $query) {
    config(['services.whatsapp.platform.webhook_verify_token' => 'platform-verify-token']);

    $response = $this->get(route('webhooks.whatsapp.tech-provider').'?'.$query);

    $response->assertForbidden();
})->with([
    'wrong mode' => 'hub.mode=unsubscribe&hub.verify_token=platform-verify-token&hub.challenge=platform-challenge',
    'wrong token' => 'hub.mode=subscribe&hub.verify_token=unknown-token&hub.challenge=platform-challenge',
    'missing mode' => 'hub.verify_token=platform-verify-token&hub.challenge=platform-challenge',
    'missing token' => 'hub.mode=subscribe&hub.challenge=platform-challenge',
    'missing challenge' => 'hub.mode=subscribe&hub.verify_token=platform-verify-token',
]);

it('returns 403 when the platform verify token is not configured', function () {
    config(['services.whatsapp.platform.webhook_verify_token' => null]);

    $response = $this->get(route('webhooks.whatsapp.tech-provider').'?hub.mode=subscribe&hub.verify_token=platform-verify-token&hub.challenge=platform-challenge');

    $response->assertForbidden();
});

it('returns 403 when an own app verify token is used on the tech provider route', function () {
    config(['services.whatsapp.platform.webhook_verify_token' => 'platform-verify-token']);
    WhatsAppAccount::factory()->create(['verify_token' => 'own-app-verify-token']);

    $response = $this->get(route('webhooks.whatsapp.tech-provider').'?hub.mode=subscribe&hub.verify_token=own-app-verify-token&hub.challenge=challenge');

    $response->assertForbidden();
});

it('dispatches tech provider events for a known WABA signed by the platform app', function () {
    config(['services.whatsapp.platform.app_secret' => 'platform-app-secret']);
    $account = WhatsAppAccount::factory()->techProvider()->create();
    $channel = Channel::factory()->for($account->team)->create([
        'type' => ChannelType::WhatsApp,
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create();
    $body = json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $account->waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => $phoneNumber->phone_number_id],
                    'messages' => [[
                        'from' => '5511999999001',
                        'id' => 'tech-provider-message-1',
                        'type' => 'text',
                        'text' => ['body' => 'Mensagem do Tech Provider.'],
                    ]],
                ],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'platform-app-secret');
    Queue::fake([ProcessWhatsAppWebhook::class]);

    $response = postTechProviderWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    Queue::assertPushed(ProcessWhatsAppWebhook::class, fn (ProcessWhatsAppWebhook $job): bool => $job->channelId === $channel->getKey());
});

it('returns 200 and discards unknown WABAs and own app accounts on the tech provider route', function () {
    config(['services.whatsapp.platform.app_secret' => 'platform-app-secret']);
    [$ownAppAccount, $ownAppPhoneNumber] = createWhatsAppWebhookAccount();
    $body = json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => $ownAppAccount->waba_id,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $ownAppPhoneNumber->phone_number_id],
                        'messages' => [['id' => 'own-app-message-1', 'type' => 'text']],
                    ],
                ]],
            ],
            [
                'id' => 'unknown-tech-provider-waba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'unknown-phone-number'],
                        'messages' => [['id' => 'unknown-message-1', 'type' => 'text']],
                    ],
                ]],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'platform-app-secret');
    Queue::fake([ProcessWhatsAppWebhook::class]);

    $response = postTechProviderWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    Queue::assertNotPushed(ProcessWhatsAppWebhook::class);
});

it('returns 403 when the tech provider route receives a signature from an own app account', function () {
    config(['services.whatsapp.platform.app_secret' => 'platform-app-secret']);
    [$account, $phoneNumber] = createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody($account->waba_id, $phoneNumber->phone_number_id);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = postTechProviderWhatsAppWebhook($this, $body, $signature);

    $response->assertForbidden();
});

it('returns 403 when the platform app secret is not configured', function () {
    config(['services.whatsapp.platform.app_secret' => null]);
    $body = '{}';
    $signature = 'sha256='.hash_hmac('sha256', $body, 'platform-app-secret');

    $response = postTechProviderWhatsAppWebhook($this, $body, $signature);

    $response->assertForbidden();
});

it('returns 403 when the own app route receives the platform app signature', function () {
    config(['services.whatsapp.platform.app_secret' => 'platform-app-secret']);
    [$account, $phoneNumber] = createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody($account->waba_id, $phoneNumber->phone_number_id);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'platform-app-secret');

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertForbidden();
});

it('returns 200 for a valid signed event resolved through the phone number and channel', function () {
    [$account, $phoneNumber] = createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody($account->waba_id, $phoneNumber->phone_number_id);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
});

it('returns 403 for an invalid or missing event signature', function (?string $signature) {
    [$account, $phoneNumber] = createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody($account->waba_id, $phoneNumber->phone_number_id);

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertForbidden();
})->with([
    'invalid signature' => 'sha256=invalid',
    'missing signature' => null,
]);

it('returns 200 and discards a valid signed event for an unknown phone number', function () {
    [$account] = createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody($account->waba_id, 'unknown-phone-number');
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseCount('messages', 0);
});

it('returns 200 and discards a valid signed event for an unknown WABA', function () {
    createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody('unknown-waba', 'unknown-phone-number');
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseCount('messages', 0);
});

it('returns 403 when an unknown WABA event has an invalid signature', function () {
    createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody('unknown-waba', 'unknown-phone-number');

    $response = postOwnAppWhatsAppWebhook($this, $body, 'sha256=invalid');

    $response->assertForbidden();
});

it('returns 200 and discards a valid signed event with an unknown field', function () {
    [$account, $phoneNumber] = createWhatsAppWebhookAccount();
    $body = whatsappWebhookBody($account->waba_id, $phoneNumber->phone_number_id, 'unknown_field');
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseCount('messages', 0);
});

it('keeps message and status jobs unique when they share a provider ID', function () {
    [$account, $phoneNumber] = createWhatsAppWebhookAccount();
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $account->waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => [
                        'phone_number_id' => $phoneNumber->phone_number_id,
                    ],
                    'messages' => [[
                        'from' => '5511999999001',
                        'id' => 'shared-provider-id',
                        'type' => 'text',
                        'text' => ['body' => 'Mensagem recebida.'],
                    ]],
                    'statuses' => [[
                        'id' => 'shared-provider-id',
                        'status' => 'sent',
                        'recipient_id' => '5511999999001',
                    ]],
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');
    Queue::fake([ProcessWhatsAppWebhook::class]);

    $response = postOwnAppWhatsAppWebhook($this, $body, $signature);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    Queue::assertPushed(ProcessWhatsAppWebhook::class, 2);
    $uniqueIds = Queue::pushed(ProcessWhatsAppWebhook::class)
        ->map(fn (ProcessWhatsAppWebhook $job): string => $job->uniqueId())
        ->sort()
        ->values()
        ->all();

    expect($uniqueIds)->toBe([
        $phoneNumber->channel_id.':messages:messages:shared-provider-id',
        $phoneNumber->channel_id.':messages:statuses:shared-provider-id',
    ]);
});

it('returns 429 after 300 webhook requests from the same source', function () {
    for ($attempt = 0; $attempt < 300; $attempt++) {
        $this->get(route('webhooks.whatsapp.app-proprio'));
    }

    $response = $this->get(route('webhooks.whatsapp.app-proprio'));

    $response->assertTooManyRequests();
});
