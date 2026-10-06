<?php

use App\Consent\ContactConsentService;
use App\Enums\ChannelType;
use App\Enums\ConsentAction;
use App\Enums\ConsentPurpose;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Enums\WhatsAppTemplateStatus;
use App\Events\HumanConversationUpdated;
use App\Jobs\RespondToWhatsAppMessage;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * @return array<string, mixed>
 */
function whatsappConformanceFixture(string $fixtureDirectory, string $fixture): array
{
    $path = base_path("tests/Fixtures/whatsapp/{$fixtureDirectory}/{$fixture}.json");

    TestCase::assertFileExists($path, "The {$fixtureDirectory} channel is missing the {$fixture} webhook fixture.");
    $contents = file_get_contents($path);
    TestCase::assertIsString($contents);

    return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
}

function createWhatsAppConformanceChannel(
    ChannelType $channelType,
    string $wabaId = 'waba-fixture',
    string $phoneNumberId = 'official-phone-number-id',
    string $instanceName = 'local-development',
): Channel {
    $channel = Channel::factory()->for(Team::factory())->create([
        'type' => $channelType,
    ]);

    if ($channelType === ChannelType::WhatsApp) {
        $account = WhatsAppAccount::factory()->for($channel->team, 'team')->create([
            'app_secret' => 'conformance-app-secret',
            'waba_id' => $wabaId,
        ]);
        $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
            'phone_number_id' => $phoneNumberId,
            'whatsapp_account_id' => $account->getKey(),
        ]);
    } else {
        WhatsAppTestConnection::factory()->for($channel)->create([
            'instance_name' => $instanceName,
        ]);
    }

    return $channel;
}

/**
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function postWhatsAppConformanceWebhook(
    TestCase $testCase,
    ChannelType $channelType,
    string $routeName,
    array $payload,
): TestResponse {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'media unavailable'], 503),
    ]);

    if ($channelType === ChannelType::WhatsAppTest) {
        return $testCase->postJson(route($routeName), $payload);
    }

    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return $testCase->call('POST', route($routeName), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'conformance-app-secret'),
    ], $body);
}

dataset('WhatsApp webhook channels', [
    'official WhatsApp' => [ChannelType::WhatsApp, 'webhooks.whatsapp.app-proprio', 'oficial'],
    'test WhatsApp' => [ChannelType::WhatsAppTest, 'webhooks.whatsapp-test', 'testes'],
]);

it('creates the contact and records each core incoming message', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
    string $fixture,
    string $eventType,
    ?string $content,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $payload = whatsappConformanceFixture($fixtureDirectory, $fixture);

    $response = postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('contacts', [
        'team_id' => $channel->team_id,
        'phone' => '+5511999999999',
        'name' => 'Ana Souza',
    ]);
    $expectedContent = match (true) {
        $eventType === 'video' => $content ?? 'O Contato enviou um vídeo.',
        $channelType === ChannelType::WhatsApp && in_array($eventType, ['audio', 'image', 'document'], true) => filled($content)
            ? "{$content}\n\nNão foi possível processar esta mídia. O atendimento continuará com as demais mensagens."
            : 'Não foi possível processar esta mídia. O atendimento continuará com as demais mensagens.',
        default => $content,
    };

    $this->assertDatabaseHas('messages', [
        'team_id' => $channel->team_id,
        'channel_id' => $channel->id,
        'payload_type' => $eventType,
        'direction' => MessageDirection::Incoming->value,
        'content' => $expectedContent,
    ]);
})->with('WhatsApp webhook channels')->with([
    'text' => ['message-text', 'text', 'Olá, preciso de ajuda com meu pedido.'],
    'image' => ['message-image', 'image', 'Esta é a foto do problema.'],
    'audio' => ['message-audio', 'audio', null],
    'video' => ['message-video', 'video', 'Um vídeo do defeito.'],
    'document' => ['message-document', 'document', 'Segue o comprovante.'],
]);

it('records official template button replies as text and preserves their payload', function (
    string $fixture,
    string $providerMessageId,
    string $content,
    string $buttonPayload,
) {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $agent = Agent::factory()->for($channel->team)->create();
    $channel->update(['agent_id' => $agent->getKey()]);
    Queue::fake([RespondToWhatsAppMessage::class]);

    $response = postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        whatsappConformanceFixture('oficial', $fixture),
    );

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $message = Message::withoutGlobalScopes()
        ->where('channel_id', $channel->getKey())
        ->where('provider_message_id', $providerMessageId)
        ->firstOrFail();
    expect($message->payload_type)->toBe('text')
        ->and($message->content)->toBe($content)
        ->and($message->getAttribute('button_payload'))->toBe($buttonPayload);
    Queue::assertPushed(RespondToWhatsAppMessage::class, fn (RespondToWhatsAppMessage $job): bool => $job->channelId === $channel->getKey() && $job->contactId === $message->contact_id
    );
})->with([
    'template quick reply button' => [
        'message-template-button',
        'wamid.ABGGFlCGg0cvAgo-sHWxBA2VFD_S',
        'No',
        'No-Button-Payload',
    ],
    'interactive button reply' => [
        'message-interactive-button-reply',
        'wamid.interactive-button-reply-1',
        'Confirmar consulta',
        'confirm-appointment-v2',
    ],
]);

it('does not record the same provider message twice', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    createWhatsAppConformanceChannel($channelType);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'message-text');

    postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();
    postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();

    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseCount('messages', 1);
})->with('WhatsApp webhook channels');

it('creates, replaces, and removes the reaction on its target message', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '5511999999999',
    ]);
    $providerMessageId = $channelType === ChannelType::WhatsApp
        ? 'official-reacted-message-1'
        : 'test-reacted-message-1';
    $targetMessage = Message::factory()
        ->for($contact)
        ->for($channel)
        ->create(['provider_message_id' => $providerMessageId]);
    $agent = Agent::factory()->for($channel->team)->create();
    $channel->update(['agent_id' => $agent->getKey()]);
    Queue::fake([RespondToWhatsAppMessage::class]);
    Event::fake([HumanConversationUpdated::class]);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'message-reaction');

    foreach (['👍', '❤️', ''] as $index => $emoji) {
        if ($channelType === ChannelType::WhatsApp) {
            $payload['entry'][0]['changes'][0]['value']['messages'][0]['id'] = 'official-reaction-event-'.$index;
            $payload['entry'][0]['changes'][0]['value']['messages'][0]['reaction']['message_id'] = $providerMessageId;
            $payload['entry'][0]['changes'][0]['value']['messages'][0]['reaction']['emoji'] = $emoji;
        } else {
            $payload['data']['Info']['ID'] = 'test-reaction-event-'.$index;
            $payload['data']['Message']['reactionMessage']['key']['id'] = $providerMessageId;
            $payload['data']['Message']['reactionMessage']['text'] = $emoji;
        }

        postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();

        $updatedMessage = $targetMessage->fresh();

        expect($updatedMessage->reaction_emoji)->toBe($emoji === '' ? null : $emoji)
            ->and($updatedMessage->reacted_at !== null)->toBe($emoji !== '');
    }

    $this->assertDatabaseCount('messages', 1);
    Queue::assertNotPushed(RespondToWhatsAppMessage::class);
    Event::assertDispatched(HumanConversationUpdated::class, fn (HumanConversationUpdated $event): bool => $event->teamId === $channel->team_id
        && $event->contactId === $contact->id
        && $event->channelId === $channel->id
        && $event->action === 'message'
    );
})->with('WhatsApp webhook channels');

it('discards a reaction when the provider message belongs to another channel', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $otherChannel = createWhatsAppConformanceChannel(
        $channelType,
        'other-waba-fixture',
        'other-phone-number-id',
        'other-local-development',
    );

    $contact = Contact::factory()->for($otherChannel->team)->create();
    $providerMessageId = 'provider-message-on-other-channel';
    $targetMessage = Message::factory()
        ->for($contact)
        ->for($otherChannel)
        ->create(['provider_message_id' => $providerMessageId]);
    $agent = Agent::factory()->for($channel->team)->create();
    $channel->update(['agent_id' => $agent->getKey()]);
    Queue::fake([RespondToWhatsAppMessage::class]);
    Event::fake([HumanConversationUpdated::class]);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'message-reaction');

    if ($channelType === ChannelType::WhatsApp) {
        $payload['entry'][0]['changes'][0]['value']['messages'][0]['reaction']['message_id'] = $providerMessageId;
    } else {
        $payload['data']['Message']['reactionMessage']['key']['id'] = $providerMessageId;
    }

    postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();

    expect($targetMessage->fresh()->reaction_emoji)->toBeNull()
        ->and($targetMessage->fresh()->reacted_at)->toBeNull();
    $this->assertDatabaseCount('messages', 1);
    Queue::assertNotPushed(RespondToWhatsAppMessage::class);
    Event::assertNotDispatched(HumanConversationUpdated::class);
})->with('WhatsApp webhook channels');

it('updates official template statuses and records the rejection explanation', function (
    string $event,
    WhatsAppTemplateStatus $expectedStatus,
    ?string $reason,
) {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $account = $channel->whatsappPhoneNumber->whatsappAccount;
    $template = WhatsAppTemplate::factory()->for($account, 'account')->rejected()->create([
        'meta_template_id' => 'meta-template-ticket10',
        'rejection_explanation' => 'Explicação anterior.',
    ]);
    $payload = whatsappConformanceFixture('oficial', 'template-status-update');
    $payload['entry'][0]['changes'][0]['value']['event'] = $event;
    $payload['entry'][0]['changes'][0]['value']['reason'] = $reason;

    $response = postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    );

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    expect($template->fresh()->status)->toBe($expectedStatus)
        ->and($template->fresh()->rejection_reason)->toBe($reason)
        ->and($template->fresh()->rejection_explanation)->toBe($reason === null
            ? null
            : 'A estrutura do Template não segue o formato aceito. Confira as variáveis, botões e limites de caracteres.');
    $this->assertDatabaseHas('whatsapp_template_histories', [
        'whatsapp_template_id' => $template->getKey(),
        'event' => 'status_changed',
        'new_status' => $expectedStatus->value,
        'reason' => $reason,
        'explanation' => $reason === null
            ? null
            : 'A estrutura do Template não segue o formato aceito. Confira as variáveis, botões e limites de caracteres.',
    ]);
})->with([
    'approval' => ['APPROVED', WhatsAppTemplateStatus::Approved, null],
    'rejection' => ['REJECTED', WhatsAppTemplateStatus::Rejected, 'INCORRECT_FORMAT'],
    'pause' => ['PAUSED', WhatsAppTemplateStatus::Paused, null],
    'disable' => ['DISABLED', WhatsAppTemplateStatus::Disabled, null],
]);

it('updates an official template category and records the old and new categories', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $account = $channel->whatsappPhoneNumber->whatsappAccount;
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'meta_template_id' => 'meta-template-ticket10',
        'requested_category' => 'utility',
        'assigned_category' => 'utility',
    ]);
    $payload = whatsappConformanceFixture('oficial', 'template-category-update');

    $response = postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    );

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    expect($template->fresh()->assigned_category->value)->toBe('marketing')
        ->and($template->fresh()->requested_category->value)->toBe('utility');
    $this->assertDatabaseHas('whatsapp_template_histories', [
        'whatsapp_template_id' => $template->getKey(),
        'event' => 'category_changed',
        'old_category' => 'utility',
        'new_category' => 'marketing',
    ]);
});

it('updates an official template quality rating and records its history', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $account = $channel->whatsappPhoneNumber->whatsappAccount;
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'meta_template_id' => 'meta-template-ticket10',
        'quality_rating' => 'GREEN',
    ]);
    $payload = whatsappConformanceFixture('oficial', 'template-quality-update');

    $response = postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    );

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    expect($template->fresh()->quality_rating)->toBe('RED');
    $this->assertDatabaseHas('whatsapp_template_histories', [
        'whatsapp_template_id' => $template->getKey(),
        'event' => 'quality_changed',
        'old_quality_rating' => 'GREEN',
        'new_quality_rating' => 'RED',
    ]);
});

it('syncs the matching account when a template webhook references an unknown template', function () {
    Http::preventStrayRequests();
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $account = $channel->whatsappPhoneNumber->whatsappAccount;
    $foreignChannel = createWhatsAppConformanceChannel(
        ChannelType::WhatsApp,
        'waba-foreign-fixture',
        'foreign-phone-number-id',
    );
    $foreignAccount = $foreignChannel->whatsappPhoneNumber->whatsappAccount;
    $foreignTemplate = WhatsAppTemplate::factory()->for($foreignAccount, 'account')->create([
        'meta_template_id' => 'meta-template-unknown-ticket10',
        'status' => WhatsAppTemplateStatus::Approved,
    ]);
    $payload = whatsappConformanceFixture('oficial', 'template-status-update');
    $payload['entry'][0]['changes'][0]['value']['message_template_id'] = 'meta-template-unknown-ticket10';
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$account->waba_id.'/message_templates';
    Http::fake([
        'graph.facebook.com/*/'.$account->waba_id.'/message_templates*' => Http::response([
            'data' => [[
                'id' => 'meta-template-unknown-ticket10',
                'name' => 'consulta_amanha',
                'language' => 'pt_BR',
                'status' => 'REJECTED',
                'category' => 'UTILITY',
                'rejected_reason' => 'INCORRECT_FORMAT',
                'components' => [
                    ['type' => 'BODY', 'text' => 'Sua consulta é amanhã.'],
                ],
            ]],
        ]),
    ]);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $response = $this->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'conformance-app-secret'),
    ], $body);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $syncedTemplate = WhatsAppTemplate::query()
        ->where('whatsapp_account_id', $account->getKey())
        ->where('meta_template_id', 'meta-template-unknown-ticket10')
        ->firstOrFail();
    expect($syncedTemplate->status)->toBe(WhatsAppTemplateStatus::Rejected)
        ->and($foreignTemplate->fresh()->status)->toBe(WhatsAppTemplateStatus::Approved);
    $this->assertDatabaseHas('whatsapp_template_histories', [
        'whatsapp_template_id' => $syncedTemplate->getKey(),
        'event' => 'imported',
    ]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $endpoint.'?limit=100');
});

it('records every message and status across entries and changes and starts a response for each contact', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $agent = Agent::factory()->for($channel->team)->create();
    $channel->update(['agent_id' => $agent->getKey()]);
    $secondChannel = createWhatsAppConformanceChannel(
        ChannelType::WhatsApp,
        'waba-fixture-second',
        'official-phone-number-id-second',
    );
    $secondAgent = Agent::factory()->for($secondChannel->team)->create();
    $secondChannel->update(['agent_id' => $secondAgent->getKey()]);
    $secondAccount = $secondChannel->whatsappPhoneNumber->whatsappAccount;
    $secondAccount->update(['app_secret' => 'conformance-app-secret']);
    $statusContact = Contact::factory()->for($secondChannel->team)->create([
        'phone' => '5511999998000',
    ]);
    $outgoingMessage = Message::factory()
        ->for($statusContact)
        ->for($secondChannel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'official-outgoing-message',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    $payload = whatsappConformanceFixture('oficial', 'multiple-events');
    Queue::fake([RespondToWhatsAppMessage::class]);

    $response = postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    );

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseCount('messages', 4);
    $this->assertDatabaseHas('messages', [
        'channel_id' => $channel->id,
        'provider_message_id' => 'official-message-multiple-1',
        'content' => 'Primeira mensagem do lote.',
    ]);
    $this->assertDatabaseHas('messages', [
        'channel_id' => $channel->id,
        'provider_message_id' => 'official-message-multiple-2',
        'content' => 'Segunda mensagem do lote.',
    ]);
    $this->assertDatabaseHas('messages', [
        'channel_id' => $secondChannel->id,
        'provider_message_id' => 'official-message-multiple-3',
        'content' => 'Mensagem de outro entry.',
    ]);
    $this->assertDatabaseMissing('messages', [
        'provider_message_id' => 'official-message-unknown-field',
    ]);
    expect($outgoingMessage->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Read);

    $expectedContactIds = Contact::query()
        ->whereIn('phone', ['+5511999999001', '+5511999999002', '+5511999999004'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
    $dispatchedContactIds = Queue::pushed(RespondToWhatsAppMessage::class)
        ->pluck('contactId')
        ->sort()
        ->values()
        ->all();

    expect($dispatchedContactIds)->toBe($expectedContactIds);
});

it('records unsupported messages by raw provider type without sending them to the Agent', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
    string $unsupportedType,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $agent = Agent::factory()->for($channel->team)->create(['is_active' => true]);
    $channel->update(['agent_id' => $agent->getKey()]);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'message-unsupported');
    Queue::fake([RespondToWhatsAppMessage::class]);

    if ($channelType === ChannelType::WhatsApp) {
        $message = &$payload['entry'][0]['changes'][0]['value']['messages'][0];
        $message['type'] = $unsupportedType;
        $message[$unsupportedType] = match ($unsupportedType) {
            'sticker' => ['id' => 'media-sticker-1'],
            'location' => ['latitude' => -23.55, 'longitude' => -46.63],
            'contacts' => [['name' => ['formatted_name' => 'Ana Souza']]],
            default => ['value' => 'provider-specific payload'],
        };
        $expectedEventType = $unsupportedType;
    } else {
        $unsupportedPayload = match ($unsupportedType) {
            'sticker' => ['stickerMessage' => ['mimetype' => 'image/webp']],
            'location' => ['locationMessage' => ['degreesLatitude' => -23.55, 'degreesLongitude' => -46.63]],
            'contacts' => ['contactMessage' => ['displayName' => 'Ana Souza', 'vcard' => 'BEGIN:VCARD']],
            default => ['futureMessage' => ['value' => 'provider-specific payload']],
        };
        $payload['data']['Info']['MediaType'] = $unsupportedType;
        $payload['data']['Message'] = $unsupportedPayload;
        $expectedEventType = array_key_first($unsupportedPayload);
    }

    $response = postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload);

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'channel_id' => $channel->id,
        'payload_type' => $expectedEventType,
        'direction' => MessageDirection::Incoming->value,
        'content' => null,
    ]);
    Queue::assertNotPushed(RespondToWhatsAppMessage::class);
    Http::assertSentCount(0);
})->with('WhatsApp webhook channels')->with([
    'sticker' => ['sticker'],
    'location' => ['location'],
    'contact card' => ['contacts'],
    'unknown type' => ['future_message'],
]);

it('reuses the E.164 contact when the sender omits the Brazilian ninth digit', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '+5531986441470',
    ]);
    $payload = json_decode(
        str_replace('5511999999999', '553186441470', json_encode(whatsappConformanceFixture($fixtureDirectory, 'message-text'), JSON_THROW_ON_ERROR)),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();

    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseHas('messages', [
        'contact_id' => $contact->id,
        'direction' => MessageDirection::Incoming->value,
    ]);
})->with('WhatsApp webhook channels');

it('updates the matching outgoing message delivery status', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '5511999999999',
    ]);
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'outgoing-message-1',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'delivery-status');

    $response = postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload);

    $response->assertOk();
    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Read);
    $this->assertDatabaseCount('messages', 1);
})->with('WhatsApp webhook channels');

it('only advances official delivery status when webhook events arrive out of order', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $contact = Contact::factory()->for($channel->team)->create();
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'official-outgoing-progress',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    $payload = whatsappConformanceFixture('oficial', 'delivery-status');
    $payload['entry'][0]['changes'][0]['value']['statuses'][0]['id'] = $message->provider_message_id;

    foreach ([
        'delivered' => MessageDeliveryStatus::Delivered,
        'sent' => MessageDeliveryStatus::Delivered,
        'read' => MessageDeliveryStatus::Read,
        'delivered_after_read' => MessageDeliveryStatus::Read,
    ] as $status => $expectedStatus) {
        $payload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] = str_replace('_after_read', '', $status);

        postWhatsAppConformanceWebhook(
            $this,
            ChannelType::WhatsApp,
            'webhooks.whatsapp.app-proprio',
            $payload,
        )->assertOk();

        expect($message->fresh()->delivery_status)->toBe($expectedStatus);
    }
});

it('records a translated failure reason and Meta code for official failed statuses', function (int $failureCode, string $failureReason) {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $contact = Contact::factory()->for($channel->team)->create();
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'official-outgoing-failed-'.$failureCode,
            'delivery_status' => MessageDeliveryStatus::Delivered,
        ]);
    $payload = whatsappConformanceFixture('oficial', 'delivery-status');
    $payload['entry'][0]['changes'][0]['value']['statuses'][0] = [
        'id' => $message->provider_message_id,
        'status' => 'failed',
        'errors' => [['code' => $failureCode]],
    ];

    postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    )->assertOk();

    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Failed)
        ->and($message->fresh()->failure_code)->toBe($failureCode)
        ->and($message->fresh()->failure_reason)->toBe($failureReason);
})->with([
    'closed 24 hour window' => [131047, 'Janela de atendimento de 24 horas encerrada.'],
    'recipient cannot receive messages' => [131026, 'Este número não pode receber mensagens pelo WhatsApp.'],
    'media rejected' => [131053, 'A Meta recusou a mídia enviada.'],
    'marketing blocked' => [131050, 'Contato bloqueou mensagens de marketing.'],
    'unknown failure' => [999999, 'Não foi possível entregar esta mensagem pelo WhatsApp.'],
]);

it('revokes marketing consent when Meta reports a marketing block', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $contact = Contact::factory()->for($channel->team)->create();
    $consent = app(ContactConsentService::class);
    $consent->grant($contact, ConsentPurpose::Marketing, 'settings');
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'official-marketing-block',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    $payload = whatsappConformanceFixture('oficial', 'delivery-status');
    $payload['entry'][0]['changes'][0]['value']['statuses'][0] = [
        'id' => $message->provider_message_id,
        'status' => 'failed',
        'errors' => [['code' => 131050]],
    ];

    postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    )->assertOk();

    expect($consent->isGranted($contact, ConsentPurpose::Marketing))->toBeFalse();
    $this->assertDatabaseHas('contact_consents', [
        'team_id' => $channel->team_id,
        'contact_id' => $contact->id,
        'purpose' => ConsentPurpose::Marketing->value,
        'action' => ConsentAction::Revoked->value,
        'source' => 'meta_webhook',
    ]);
});

it('ignores official pricing status updates', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsApp);
    $contact = Contact::factory()->for($channel->team)->create();
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'official-outgoing-pricing',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    $payload = whatsappConformanceFixture('oficial', 'delivery-status');
    $payload['entry'][0]['changes'][0]['value']['statuses'][0]['id'] = $message->provider_message_id;
    $payload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] = 'pricing';

    postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsApp,
        'webhooks.whatsapp.app-proprio',
        $payload,
    )->assertOk();

    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sent);
});

it('does not replace a failed test message with a read receipt', function () {
    $channel = createWhatsAppConformanceChannel(ChannelType::WhatsAppTest);
    $contact = Contact::factory()->for($channel->team)->create();
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'test-outgoing-failed',
            'delivery_status' => MessageDeliveryStatus::Failed,
            'failure_code' => 131047,
            'failure_reason' => 'Janela de atendimento de 24 horas encerrada.',
        ]);
    $payload = whatsappConformanceFixture('testes', 'delivery-status');
    $payload['data']['MessageIDs'] = [$message->provider_message_id];

    postWhatsAppConformanceWebhook(
        $this,
        ChannelType::WhatsAppTest,
        'webhooks.whatsapp-test',
        $payload,
    )->assertOk();

    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Failed)
        ->and($message->fresh()->failure_code)->toBe(131047)
        ->and($message->fresh()->failure_reason)->toBe('Janela de atendimento de 24 horas encerrada.');
});
