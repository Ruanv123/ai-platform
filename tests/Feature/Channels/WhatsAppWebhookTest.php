<?php

use App\Models\WhatsAppNumber;

it('returns the challenge and records verification for a matching public handshake', function () {
    $whatsappNumber = WhatsAppNumber::factory()->create([
        'verify_token' => 'known-verify-token',
    ]);
    $verificationTime = now()->setSeconds(12)->setMicrosecond(0);
    $this->travelTo($verificationTime);

    $response = $this->get(route('webhooks.whatsapp').'?hub.mode=subscribe&hub.verify_token=known-verify-token&hub.challenge=challenge-value');

    $response->assertOk()
        ->assertContent('challenge-value')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $verifiedNumber = WhatsAppNumber::withoutGlobalScopes()->findOrFail($whatsappNumber->id);
    expect($verifiedNumber->webhook_verified_at)->toEqual($verificationTime);
});

it('returns 403 without recording verification for an invalid handshake', function (string $query) {
    $whatsappNumber = WhatsAppNumber::factory()->create([
        'verify_token' => 'known-verify-token',
    ]);

    $response = $this->get(route('webhooks.whatsapp').'?'.$query);

    $response->assertForbidden();
    $unverifiedNumber = WhatsAppNumber::withoutGlobalScopes()->findOrFail($whatsappNumber->id);
    expect($unverifiedNumber->webhook_verified_at)->toBeNull();
})->with([
    'wrong mode' => 'hub.mode=unsubscribe&hub.verify_token=known-verify-token&hub.challenge=challenge-value',
    'unknown token' => 'hub.mode=subscribe&hub.verify_token=unknown-token&hub.challenge=challenge-value',
    'missing mode' => 'hub.verify_token=known-verify-token&hub.challenge=challenge-value',
    'missing token' => 'hub.mode=subscribe&hub.challenge=challenge-value',
    'missing challenge' => 'hub.mode=subscribe&hub.verify_token=known-verify-token',
]);

function whatsappWebhookBody(string $phoneNumberId = '15551234567890'): string
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

it('returns 200 for a valid signed event without processing it', function () {
    $whatsappNumber = WhatsAppNumber::factory()->create([
        'phone_number_id' => '15551234567890',
        'app_secret' => 'app-secret',
    ]);
    $body = whatsappWebhookBody();
    $signature = 'sha256=acc8908448ebf5b966907c9d60ceb9cec6c3c743940284b26924ded7fd77b95d';

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    expect(WhatsAppNumber::withoutGlobalScopes()->findOrFail($whatsappNumber->id)->webhook_verified_at)
        ->toBeNull();
});

it('returns 403 for an invalid or missing event signature', function (?string $phoneNumberId, ?string $signature) {
    WhatsAppNumber::factory()->create([
        'phone_number_id' => '15551234567890',
        'app_secret' => 'app-secret',
    ]);
    $body = whatsappWebhookBody($phoneNumberId ?? '15551234567890');

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ]), $body);

    $response->assertForbidden();
})->with([
    'invalid signature' => ['15551234567890', 'sha256=invalid'],
    'missing signature' => ['15551234567890', null],
]);

it('returns 403 for a valid signature from an unknown phone number', function () {
    WhatsAppNumber::factory()->create([
        'phone_number_id' => '15551234567890',
        'app_secret' => 'app-secret',
    ]);
    $body = whatsappWebhookBody('unknown-phone-number');

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=34cd9e8d1eab018ed78d27106063ac95b534eb0895e18db679bc08856528ae9f',
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
