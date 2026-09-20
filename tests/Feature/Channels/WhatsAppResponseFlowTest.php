<?php

use App\Ai\Agents\WhatsAppAgent;
use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use RuntimeException;

function responseFlowTestFixture(string $name, string $provider = 'testes'): array
{
    return json_decode(
        file_get_contents(base_path("tests/Fixtures/whatsapp/{$provider}/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function createResponseFlowTestChannel(): Channel
{
    $team = Team::factory()->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WhatsAppTest,
        'agent_id' => $agent->getKey(),
    ]);

    WhatsAppTestConnection::factory()->for($channel)->create([
        'service_url' => 'https://evolution.test',
        'instance_name' => 'local-development',
    ]);

    return $channel;
}

function createOfficialResponseFlowTestChannel(): Channel
{
    $team = Team::factory()->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WhatsApp,
        'agent_id' => $agent->getKey(),
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => 'official-phone-number-id',
    ]);

    WhatsAppAccount::factory()->for($phoneNumber, 'phoneNumber')->create([
        'access_token' => 'official-access-token',
        'app_secret' => 'official-app-secret',
    ]);

    return $channel;
}

it('uses the saved Agent instructions and the Contact history for a response', function () {
    $channel = createResponseFlowTestChannel();
    $channel->agent()->update([
        'name' => 'Lia',
        'instructions' => ['Consulte o histórico antes de responder.'],
    ]);
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '5511999999999',
    ]);
    Message::factory()->for($contact)->for($channel)->incoming()->responded()->create([
        'content' => 'Meu pedido é o número 123.',
    ]);
    WhatsAppAgent::fake(['Encontrei o seu pedido.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => Http::response([
            'key' => ['id' => 'test-outgoing-message-history'],
        ]),
    ]);

    $response = $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    );

    $response->assertOk();
    WhatsAppAgent::assertPrompted(function ($prompt): bool {
        $history = collect($prompt->agent->messages())->pluck('content')->all();

        return str_contains((string) $prompt->agent->instructions(), 'Consulte o histórico antes de responder.')
            && $history === ['Meu pedido é o número 123.']
            && $prompt->prompt === 'Olá, preciso de ajuda com meu pedido.';
    });
});

it('responds to an incoming test WhatsApp message through the public webhook', function () {
    createResponseFlowTestChannel();
    WhatsAppAgent::fake(['Resposta do atendimento.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => Http::response([
            'key' => ['id' => 'test-outgoing-message-1'],
        ]),
    ]);

    $response = $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    );

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'direction' => MessageDirection::Incoming->value,
        'is_responded' => true,
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-outgoing-message-1',
        'direction' => MessageDirection::Outgoing->value,
        'content' => 'Resposta do atendimento.',
    ]);
    WhatsAppAgent::assertPrompted('Olá, preciso de ajuda com meu pedido.');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://evolution.test/message/sendText/local-development'
            && $request->data() === [
                'number' => '5511999999999',
                'text' => 'Resposta do atendimento.',
            ];
    });
});

it('does not respond twice when the provider redelivers an already handled message', function () {
    createResponseFlowTestChannel();
    WhatsAppAgent::fake(['Resposta única.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => Http::response([
            'key' => ['id' => 'test-outgoing-message-duplicate'],
        ]),
    ]);
    $payload = responseFlowTestFixture('message-text');

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseCount('messages', 2);
    WhatsAppAgent::assertPromptedTimes(1);
    Http::assertSentCount(3);
});

it('does not open a response flow when the channel has no Agent', function () {
    $channel = createResponseFlowTestChannel();
    $channel->update(['agent_id' => null]);
    WhatsAppAgent::fake(['Esta resposta não deve ser enviada.']);
    Http::preventStrayRequests();

    $response = $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    );

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'direction' => MessageDirection::Incoming->value,
        'is_responded' => false,
    ]);
    $this->assertDatabaseCount('messages', 1);
    WhatsAppAgent::assertNeverPrompted();
});

it('does not open a response flow when the channel Agent is inactive', function () {
    $channel = createResponseFlowTestChannel();
    $channel->agent()->update(['is_active' => false]);
    WhatsAppAgent::fake(['Esta resposta não deve ser enviada.']);
    Http::preventStrayRequests();

    $response = $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    );

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'direction' => MessageDirection::Incoming->value,
        'is_responded' => false,
    ]);
    $this->assertDatabaseCount('messages', 1);
    WhatsAppAgent::assertNeverPrompted();
});

it('groups messages that arrive before the response flow runs', function () {
    $channel = createResponseFlowTestChannel();
    $firstPayload = responseFlowTestFixture('message-text');
    $secondPayload = responseFlowTestFixture('message-text');
    $secondPayload['data']['key']['id'] = 'test-message-text-2';
    $secondPayload['data']['message']['conversation'] = 'Também quero saber o prazo.';
    WhatsAppAgent::fake(['Resposta única para as duas mensagens.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => Http::response([
            'key' => ['id' => 'test-outgoing-message-2'],
        ]),
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(route('webhooks.whatsapp-test'), $firstPayload)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $secondPayload)->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(5)->seconds();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);

    expect($channel->messages()->where('direction', MessageDirection::Incoming)->count())->toBe(2);
    $this->assertDatabaseCount('messages', 3);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'is_responded' => true,
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-2',
        'is_responded' => true,
    ]);
    WhatsAppAgent::assertPromptedTimes(1);

    Http::assertSentCount(5);
});

it('responds to an incoming official WhatsApp message through the public webhook', function () {
    $channel = createOfficialResponseFlowTestChannel();
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/v23.0/official-phone-number-id/messages' => Http::response([
            'messages' => [['id' => 'official-outgoing-message-1']],
        ]),
    ]);
    $payload = responseFlowTestFixture('message-text', 'oficial');
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->timestamp;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'official-app-secret');

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'official-message-text-1',
        'direction' => MessageDirection::Incoming->value,
        'is_responded' => true,
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'official-outgoing-message-1',
        'direction' => MessageDirection::Outgoing->value,
        'content' => 'Resposta oficial do atendimento.',
    ]);
    WhatsAppAgent::assertPrompted('Olá, preciso de ajuda com meu pedido.');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://graph.facebook.com/v23.0/official-phone-number-id/messages'
            && $request->data() === [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => '5511999999999',
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => 'Resposta oficial do atendimento.',
                ],
            ];
    });
});

it('retries a temporary Agent failure with backoff before sending the response', function () {
    createResponseFlowTestChannel();
    $agentAttempts = 0;
    WhatsAppAgent::fake(function () use (&$agentAttempts): string {
        $agentAttempts++;

        if ($agentAttempts === 1) {
            throw new RuntimeException('IA temporariamente indisponível.');
        }

        return 'Resposta após a recuperação.';
    });
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => Http::response([
            'key' => ['id' => 'test-outgoing-recovered'],
        ]),
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    )->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(5)->seconds();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(10)->seconds();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'is_responded' => true,
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-outgoing-recovered',
        'content' => 'Resposta após a recuperação.',
    ]);
    expect($agentAttempts)->toBe(2);
    Http::assertSentCount(3);
});

it('retries a temporary provider failure with backoff before recording the response', function () {
    createResponseFlowTestChannel();
    WhatsAppAgent::fake(fn (): string => 'Resposta após a recuperação do envio.');
    $sendAttempts = 0;
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => function () use (&$sendAttempts) {
            $sendAttempts++;

            return $sendAttempts === 1
                ? Http::response([], 503)
                : Http::response(['key' => ['id' => 'test-outgoing-provider-recovered']]);
        },
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    )->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(5)->seconds();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(10)->seconds();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-outgoing-provider-recovered',
        'content' => 'Resposta após a recuperação do envio.',
    ]);
    expect($sendAttempts)->toBe(2);
    Http::assertSentCount(4);
});

it('notifies the Contact and records the Channel error after response retries are exhausted', function () {
    $channel = createResponseFlowTestChannel();
    WhatsAppAgent::fake(function (): never {
        throw new RuntimeException('IA indisponível de forma permanente.');
    });
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/chat/markMessageAsRead/local-development' => Http::response([]),
        'https://evolution.test/chat/sendPresence/local-development' => Http::response([]),
        'https://evolution.test/message/sendText/local-development' => Http::response([
            'key' => ['id' => 'test-outgoing-failure-notice'],
        ]),
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    )->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(5)->seconds();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->travel(120)->seconds();
        Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    }

    $this->assertDatabaseHas('channels', [
        'id' => $channel->getKey(),
        'last_error' => 'IA indisponível de forma permanente.',
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-outgoing-failure-notice',
        'content' => 'Não foi possível concluir seu atendimento neste momento. Tente novamente mais tarde.',
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'is_responded' => true,
    ]);
    Http::assertSentCount(3);
});

it('does not send an official response after Meta customer-care window expires', function () {
    $channel = createOfficialResponseFlowTestChannel();
    $this->travelTo('2026-09-20 12:00:00');
    WhatsAppAgent::fake(['Resposta que não pode mais ser enviada.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/v23.0/official-phone-number-id/messages' => Http::response([
            'messages' => [['id' => 'unexpected-official-response']],
        ]),
    ]);
    $payload = responseFlowTestFixture('message-text', 'oficial');
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->subHours(25)->timestamp;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'official-app-secret');

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'official-message-text-1',
        'is_responded' => true,
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => null,
        'direction' => MessageDirection::Outgoing->value,
        'delivery_status' => 'failed',
        'content' => 'Resposta que não pode mais ser enviada.',
    ]);
    expect(Message::query()->where('provider_message_id', 'unexpected-official-response')->exists())->toBeFalse();
    WhatsAppAgent::assertPrompted('Olá, preciso de ajuda com meu pedido.');
    Http::assertSentCount(2);
});
