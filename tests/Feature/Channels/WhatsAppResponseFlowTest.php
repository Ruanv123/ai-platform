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
use App\WhatsApp\IncomingMessageResponseDispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
    config(['services.evolution.test_allowed_phones' => ['5511999999999']]);

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
    $account = WhatsAppAccount::factory()->for($team, 'team')->create([
        'access_token' => 'official-access-token',
        'app_secret' => 'official-app-secret',
        'waba_id' => 'waba-fixture',
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => 'official-phone-number-id',
        'whatsapp_account_id' => $account->getKey(),
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
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-message-history']]]),
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
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-message-1']]]),
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
        return $request->url() === 'https://evolution.test/send/text'
            && $request->data() === [
                'number' => '5511999999999',
                'text' => 'Resposta do atendimento.',
            ];
    });
});

it('only responds to the phones allowed on the test WhatsApp channel', function (array $allowedPhones, bool $shouldRespond) {
    createResponseFlowTestChannel();
    config(['services.evolution.test_allowed_phones' => $allowedPhones]);
    WhatsAppAgent::fake(['Resposta do atendimento.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/*' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-message-1']]]),
    ]);

    $this->postJson(route('webhooks.whatsapp-test'), responseFlowTestFixture('message-text'))->assertOk();

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'direction' => MessageDirection::Incoming->value,
        'is_responded' => $shouldRespond,
    ]);
    Http::assertSentCount($shouldRespond ? 3 : 0);
})->with([
    'listed phone' => [['5511999999999'], true],
    'listed phone with formatting' => [['+55 (11) 99999-9999'], true],
    'Brazilian JID without the ninth digit' => [['551199999999'], true],
    'another phone' => [['5511888888888'], false],
    'no phones configured' => [[], false],
]);

it('answers the paired account when it writes in its own conversation', function () {
    createResponseFlowTestChannel();
    WhatsAppAgent::fake(['Resposta do atendimento.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/*' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-self-chat']]]),
    ]);
    $payload = responseFlowTestFixture('message-text');
    $payload['data']['Info']['IsFromMe'] = true;
    $payload['data']['Info']['Chat'] = '11313078092023@lid';
    $payload['data']['Info']['Sender'] = '11313078092023@lid';
    $payload['data']['Info']['RecipientAlt'] = '5511999999999@s.whatsapp.net';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'direction' => MessageDirection::Incoming->value,
        'is_responded' => true,
    ]);
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-outgoing-self-chat',
        'direction' => MessageDirection::Outgoing->value,
    ]);
});

it('does not answer the message the app itself sent through the API', function () {
    createResponseFlowTestChannel();
    WhatsAppAgent::fake(['Resposta do atendimento.']);
    Http::preventStrayRequests();

    $this->postJson(route('webhooks.whatsapp-test'), responseFlowTestFixture('send-message'))->assertOk();

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-send-1',
        'direction' => MessageDirection::Outgoing->value,
    ]);
    WhatsAppAgent::assertNeverPrompted();
});

it('does not respond twice when the provider redelivers an already handled message', function () {
    createResponseFlowTestChannel();
    WhatsAppAgent::fake(['Resposta única.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-message-duplicate']]]),
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
    $secondPayload['data']['Info']['ID'] = 'test-message-text-2';
    $secondPayload['data']['Message']['conversation'] = 'Também quero saber o prazo.';
    WhatsAppAgent::fake(['Resposta única para as duas mensagens.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-message-2']]]),
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(route('webhooks.whatsapp-test'), $firstPayload)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $secondPayload)->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(IncomingMessageResponseDispatcher::BUFFER_SECONDS)->seconds();
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

    $response = $this->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
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

it('asks the Contact to describe an official video without sending its caption to the Agent', function () {
    $channel = createOfficialResponseFlowTestChannel();
    Storage::fake('whatsapp_media');
    WhatsAppAgent::fake(['Não consigo entender vídeos. Descreva o conteúdo por texto para que eu possa ajudar.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'url' => 'https://media.example/video',
            'mime_type' => 'video/mp4',
            'messages' => [['id' => 'official-outgoing-message-video-1']],
        ]),
        'https://media.example/*' => Http::response('video-content', 200, ['Content-Type' => 'video/mp4']),
    ]);
    $payload = responseFlowTestFixture('message-video', 'oficial');
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->timestamp;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'official-app-secret');

    $response = $this->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'official-outgoing-message-video-1',
        'direction' => MessageDirection::Outgoing->value,
        'content' => 'Não consigo entender vídeos. Descreva o conteúdo por texto para que eu possa ajudar.',
    ]);
    WhatsAppAgent::assertPrompted('O Contato enviou um vídeo. Responda que você não entende vídeos e peça para ele descrever o conteúdo por texto.');
});

it('asks the Contact to describe a test channel video without sending its caption to the Agent', function () {
    createResponseFlowTestChannel();
    Storage::fake('whatsapp_media');
    WhatsAppAgent::fake(['Não consigo entender vídeos. Descreva o conteúdo por texto para que eu possa ajudar.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/*' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-message-video-1']]]),
    ]);
    $payload = responseFlowTestFixture('message-video');
    $payload['data']['Info']['Timestamp'] = now()->toIso8601String();

    $response = $this->postJson(route('webhooks.whatsapp-test'), $payload);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-outgoing-message-video-1',
        'direction' => MessageDirection::Outgoing->value,
        'content' => 'Não consigo entender vídeos. Descreva o conteúdo por texto para que eu possa ajudar.',
    ]);
    WhatsAppAgent::assertPrompted('O Contato enviou um vídeo. Responda que você não entende vídeos e peça para ele descrever o conteúdo por texto.');
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
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-recovered']]]),
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    )->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(IncomingMessageResponseDispatcher::BUFFER_SECONDS)->seconds();
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
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => function () use (&$sendAttempts) {
            $sendAttempts++;

            return $sendAttempts === 1
                ? Http::response([], 503)
                : Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-provider-recovered']]]);
        },
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    )->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(IncomingMessageResponseDispatcher::BUFFER_SECONDS)->seconds();
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
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'test-outgoing-failure-notice']]]),
    ]);
    config(['queue.default' => 'database']);

    $this->postJson(
        route('webhooks.whatsapp-test'),
        responseFlowTestFixture('message-text'),
    )->assertOk();
    Artisan::call('queue:work', ['--once' => true, '--quiet' => true]);
    $this->travel(IncomingMessageResponseDispatcher::BUFFER_SECONDS)->seconds();
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

    $response = $this->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
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
