<?php

use App\Models\WhatsAppAccount;

it('returns the challenge and records verification for a matching public handshake', function () {
    $account = WhatsAppAccount::factory()->create([
        'verify_token' => 'known-verify-token',
    ]);
    $verificationTime = now()->setSeconds(12)->setMicrosecond(0);
    $this->travelTo($verificationTime);

    $response = $this->get(route('webhooks.whatsapp').'?hub.mode=subscribe&hub.verify_token=known-verify-token&hub.challenge=challenge-value');

    $response->assertOk()
        ->assertContent('challenge-value')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($account->fresh()->webhook_verified_at)->toEqual($verificationTime);
});

it('returns 403 without recording verification for an invalid handshake', function (string $query) {
    $account = WhatsAppAccount::factory()->create([
        'verify_token' => 'known-verify-token',
    ]);

    $response = $this->get(route('webhooks.whatsapp').'?'.$query);

    $response->assertForbidden();
    expect($account->fresh()->webhook_verified_at)->toBeNull();
})->with([
    'wrong mode' => 'hub.mode=unsubscribe&hub.verify_token=known-verify-token&hub.challenge=challenge-value',
    'unknown token' => 'hub.mode=subscribe&hub.verify_token=unknown-token&hub.challenge=challenge-value',
    'missing mode' => 'hub.verify_token=known-verify-token&hub.challenge=challenge-value',
    'missing token' => 'hub.mode=subscribe&hub.challenge=challenge-value',
    'missing challenge' => 'hub.mode=subscribe&hub.verify_token=known-verify-token',
]);

function whatsappWebhookBody(string $phoneNumberId): string
{
    return json_encode([
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => [
                        'phone_number_id' => $phoneNumberId,
                    ],
                ],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR);
}

it('returns 200 for a valid signed event resolved through the phone number and channel', function () {
    $account = WhatsAppAccount::factory()->create([
        'app_secret' => 'app-secret',
    ]);
    $body = whatsappWebhookBody($account->phoneNumber->phone_number_id);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
});

it('returns 403 for an invalid or missing event signature', function (?string $signature) {
    $account = WhatsAppAccount::factory()->create([
        'app_secret' => 'app-secret',
    ]);
    $body = whatsappWebhookBody($account->phoneNumber->phone_number_id);

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ]), $body);

    $response->assertForbidden();
})->with([
    'invalid signature' => 'sha256=invalid',
    'missing signature' => null,
]);

it('returns 403 for a valid signature from an unknown phone number', function () {
    $account = WhatsAppAccount::factory()->create([
        'app_secret' => 'app-secret',
    ]);
    $body = whatsappWebhookBody('unknown-phone-number');
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertForbidden();
});

it('returns 429 after 300 webhook requests from the same source', function () {
    for ($attempt = 0; $attempt < 300; $attempt++) {
        $this->get(route('webhooks.whatsapp'));
    }

    $response = $this->get(route('webhooks.whatsapp'));

    $response->assertTooManyRequests();
});
