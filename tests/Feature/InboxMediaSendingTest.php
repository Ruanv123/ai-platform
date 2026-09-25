<?php

use App\Enums\ChannelType;
use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDirection;
use App\Models\Agent;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function mediaSendingExecution(User $user, ChannelType $channelType): FlowExecution
{
    $team = $user->company();
    $channel = Channel::factory()->for($team)->connected()->create([
        'type' => $channelType,
    ]);

    if ($channelType === ChannelType::WhatsApp) {
        $account = WhatsAppAccount::factory()->for($team, 'team')->create([
            'access_token' => 'media-sending-access-token',
        ]);
        WhatsAppPhoneNumber::factory()->for($channel)->create([
            'phone_number_id' => 'media-sending-phone-id',
            'whatsapp_account_id' => $account->getKey(),
        ]);
    } else {
        WhatsAppTestConnection::factory()->for($channel)->create([
            'service_url' => 'https://evolution.test',
            'instance_name' => 'media-sending-test',
            'access_token' => 'media-sending-token',
        ]);
    }

    $contact = Contact::factory()->for($team)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'created_at' => now(),
    ]);
    $flow = Flow::factory()->for($team)->create();
    $version = FlowVersion::factory()->for($flow)->create();

    return FlowExecution::factory()
        ->for($version)
        ->for($contact)
        ->create([
            'team_id' => $team->getKey(),
            'channel_id' => $channel->getKey(),
            'status' => FlowExecutionStatus::Paused,
            'waiting_for' => 'human',
            'human_transfer_reason' => 'contact_requested',
            'wake_at' => now()->addDay(),
        ]);
}

function validOutgoingMedia(string $type): UploadedFile
{
    return match ($type) {
        'image' => UploadedFile::fake()->create('foto.jpg', 1, 'image/jpeg'),
        'document' => UploadedFile::fake()->create('orcamento.pdf', 1, 'application/pdf'),
        'video' => UploadedFile::fake()->create('demonstracao.mp4', 1, 'video/mp4'),
        'audio' => UploadedFile::fake()->create('mensagem-de-voz.ogg', 1, 'audio/ogg'),
    };
}

dataset('outgoing media by WhatsApp channel', [
    'official image' => [ChannelType::WhatsApp, 'image'],
    'official document' => [ChannelType::WhatsApp, 'document'],
    'official video' => [ChannelType::WhatsApp, 'video'],
    'official audio' => [ChannelType::WhatsApp, 'audio'],
    'test image' => [ChannelType::WhatsAppTest, 'image'],
    'test document' => [ChannelType::WhatsAppTest, 'document'],
    'test video' => [ChannelType::WhatsAppTest, 'video'],
    'test audio' => [ChannelType::WhatsAppTest, 'audio'],
]);

it('sends an outgoing media message through the conversation route', function (ChannelType $channelType, string $type) {
    Storage::fake('whatsapp_media');
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $execution = mediaSendingExecution($user, $channelType);
    $file = validOutgoingMedia($type);
    $providerMessageId = $channelType === ChannelType::WhatsApp
        ? 'meta-'.$type.'-message-id'
        : 'evolution-'.$type.'-message-id';

    if ($channelType === ChannelType::WhatsApp) {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['id' => 'meta-uploaded-media-id'])
                ->push(['messages' => [['id' => $providerMessageId]]]),
        ]);
    } else {
        Http::fake([
            'evolution.test/send/media' => Http::response([
                'data' => ['Info' => ['ID' => $providerMessageId]],
            ]),
        ]);
    }

    $this->actingAs($user)
        ->post(route('inbox.take', [
            'contact' => $execution->contact,
            'channel' => $execution->channel,
        ]))
        ->assertRedirect(route('inbox.show', [$execution->contact, $execution->channel]));

    $this->actingAs($user)
        ->post(route('inbox.messages.store', [
            'contact' => $execution->contact,
            'channel' => $execution->channel,
        ]), [
            'type' => $type,
            'media' => $file,
        ])
        ->assertRedirect(route('inbox.show', [$execution->contact, $execution->channel]));

    $outgoingMessage = Message::query()
        ->where('contact_id', $execution->contact_id)
        ->where('channel_id', $execution->channel_id)
        ->where('direction', MessageDirection::Outgoing)
        ->firstOrFail();

    $this->assertDatabaseHas('messages', [
        'id' => $outgoingMessage->getKey(),
        'payload_type' => $type,
        'provider_message_id' => $providerMessageId,
        'delivery_status' => 'sent',
        'media_filename' => $file->getClientOriginalName(),
        'media_mime_type' => $file->getMimeType(),
        'media_size' => $file->getSize(),
        'media_available' => true,
    ]);
    expect($outgoingMessage->media_path)->toStartWith('outgoing/');
    Storage::disk('whatsapp_media')->assertExists($outgoingMessage->media_path);

    if ($channelType === ChannelType::WhatsApp) {
        $phoneNumberId = 'media-sending-phone-id';
        $graphMessagesUrl = 'https://graph.facebook.com/v23.0/'.$phoneNumberId.'/messages';
        $graphMediaUrl = 'https://graph.facebook.com/v23.0/'.$phoneNumberId.'/media';

        Http::assertSent(fn (Request $request): bool => $request->url() === $graphMediaUrl
            && $request->isMultipart()
            && $request->hasFile('file', null, $file->getClientOriginalName())
            && $request->hasHeader('Authorization', 'Bearer media-sending-access-token')
            && collect($request->data())->firstWhere('name', 'messaging_product')['contents'] === 'whatsapp');
        Http::assertSent(fn (Request $request): bool => $request->url() === $graphMessagesUrl
            && $request->data()['messaging_product'] === 'whatsapp'
            && $request->data()['to'] === $execution->contact->phone
            && $request->data()['type'] === $type
            && $request->data()[$type]['id'] === 'meta-uploaded-media-id'
            && ($type !== 'audio' || $request->data()['audio']['voice'] === true));
        Http::assertSentCount(2);
    } else {
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://evolution.test/send/media'
            && $request->isMultipart()
            && $request->hasFile('file', null, $file->getClientOriginalName())
            && collect($request->data())->firstWhere('name', 'number')['contents'] === $execution->contact->phone
            && collect($request->data())->firstWhere('name', 'type')['contents'] === $type
            && collect($request->data())->firstWhere('name', 'filename')['contents'] === $file->getClientOriginalName()
            && $request->hasHeader('apikey', 'media-sending-token'));
        Http::assertSentCount(1);
    }

    $this->actingAs($user)
        ->get(route('inbox.show', [$execution->contact, $execution->channel]))
        ->assertInertia(fn ($page) => $page
            ->where('selected.messages.data.1.payloadType', $type)
            ->where('selected.messages.data.1.deliveryStatus', 'sent')
            ->where('selected.messages.data.1.mediaAvailable', true)
            ->where('selected.messages.data.1.mediaUrl', route('inbox.messages.media', [
                $execution->contact,
                $execution->channel,
                $outgoingMessage,
            ])));
})->with('outgoing media by WhatsApp channel');

it('accepts supported audio attachment formats through the conversation route', function (UploadedFile $file, bool $isVoiceNote) {
    Storage::fake('whatsapp_media');
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $execution = mediaSendingExecution($user, ChannelType::WhatsApp);
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['id' => 'meta-audio-format-upload-id'])
            ->push(['messages' => [['id' => 'meta-audio-format-message-id']]]),
    ]);

    $this->actingAs($user)
        ->post(route('inbox.take', [
            'contact' => $execution->contact,
            'channel' => $execution->channel,
        ]))
        ->assertRedirect(route('inbox.show', [$execution->contact, $execution->channel]));

    $this->actingAs($user)
        ->post(route('inbox.messages.store', [
            'contact' => $execution->contact,
            'channel' => $execution->channel,
        ]), [
            'type' => 'audio',
            'media' => $file,
        ])
        ->assertRedirect(route('inbox.show', [$execution->contact, $execution->channel]));

    $this->assertDatabaseHas('messages', [
        'channel_id' => $execution->channel_id,
        'provider_message_id' => 'meta-audio-format-message-id',
        'payload_type' => 'audio',
        'media_filename' => $file->getClientOriginalName(),
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v23.0/media-sending-phone-id/messages'
        && $request->data()['type'] === 'audio'
        && $request->data()['audio']['voice'] === $isVoiceNote);
})->with([
    'OGG/Opus' => [UploadedFile::fake()->create('gravacao.ogg', 1, 'audio/ogg'), true],
    'Opus extension' => [UploadedFile::fake()->create('gravacao.opus', 1, 'audio/ogg'), true],
    'MP3' => [UploadedFile::fake()->create('gravacao.mp3', 1, 'audio/mpeg'), false],
    'AAC' => [UploadedFile::fake()->create('gravacao.aac', 1, 'audio/aac'), false],
    'MP4' => [UploadedFile::fake()->create('gravacao.mp4', 1, 'video/mp4'), false],
    'M4A' => [UploadedFile::fake()->create('gravacao.m4a', 1, 'audio/mp4'), false],
]);

it('uploads a recipient file and sends it in an approved media header template', function () {
    Storage::fake('whatsapp_media');
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['id' => 'recipient-header-media-id'])
            ->push(['messages' => [['id' => 'media-header-template-message-id']]]),
    ]);
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $execution = mediaSendingExecution($user, ChannelType::WhatsApp);
    $account = $execution->channel->whatsappPhoneNumber->whatsappAccount;
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'name' => 'exame_pronto',
    ]);
    $template->components()->create([
        'type' => 'header',
        'format' => 'image',
        'media_handle' => 'template-example-handle',
        'position' => 0,
    ]);
    $file = UploadedFile::fake()->image('exame.png');

    $this->actingAs($user)
        ->post(route('inbox.take', inboxConversationParams($execution)))
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), [
            'type' => 'template',
            'template' => $template->name,
            'parameters' => ['Ana'],
            'media' => $file,
        ])
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    $message = Message::query()
        ->where('provider_message_id', 'media-header-template-message-id')
        ->firstOrFail();
    expect($message->payload_type)->toBe('template')
        ->and($message->media_filename)->toBe('exame.png')
        ->and($message->media_available)->toBeTrue();
    Storage::disk('whatsapp_media')->assertExists($message->media_path);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/media')
        && $request->isMultipart()
        && $request->hasFile('file', null, 'exame.png'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/messages')
        && $request->data()['template']['components'][0] === [
            'type' => 'header',
            'parameters' => [[
                'type' => 'image',
                'image' => ['id' => 'recipient-header-media-id'],
            ]],
        ]);
});

it('sends a media header template through a direct Agent conversation', function () {
    Storage::fake('whatsapp_media');
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['id' => 'direct-recipient-header-media-id'])
            ->push(['messages' => [['id' => 'direct-media-header-template-id']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->getKey()]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create([
        'access_token' => 'direct-template-media-token',
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create([
        'phone_number_id' => 'direct-template-media-phone-id',
    ]);
    $contact = Contact::factory()->for($team)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create(['created_at' => now()]);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'name' => 'direct_exame_pronto',
    ]);
    $template->components()->create([
        'type' => 'header',
        'format' => 'document',
        'media_handle' => 'document-template-example-handle',
        'position' => 0,
    ]);
    $file = UploadedFile::fake()->create('exame.pdf', 1, 'application/pdf');

    $this->actingAs($user)
        ->post(route('inbox.messages.store', ['contact' => $contact, 'channel' => $channel]), [
            'type' => 'template',
            'template' => $template->name,
            'parameters' => ['Ana'],
            'media' => $file,
        ])
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    $message = Message::query()
        ->where('provider_message_id', 'direct-media-header-template-id')
        ->firstOrFail();
    expect($message->payload_type)->toBe('template')
        ->and($message->media_filename)->toBe('exame.pdf')
        ->and($message->media_available)->toBeTrue();
    Storage::disk('whatsapp_media')->assertExists($message->media_path);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v23.0/'.$phoneNumber->phone_number_id.'/media'
        && $request->isMultipart()
        && $request->hasFile('file', null, 'exame.pdf'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v23.0/'.$phoneNumber->phone_number_id.'/messages'
        && $request->data()['template']['components'][0] === [
            'type' => 'header',
            'parameters' => [[
                'type' => 'document',
                'document' => [
                    'id' => 'direct-recipient-header-media-id',
                    'filename' => 'exame.pdf',
                ],
            ]],
        ]);
});

it('shows synchronized video header templates and blocks sending them', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $execution = mediaSendingExecution($user, ChannelType::WhatsApp);
    $account = $execution->channel->whatsappPhoneNumber->whatsappAccount;
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'name' => 'video_apresentacao',
    ]);
    $template->components()->create([
        'type' => 'header',
        'format' => 'video',
        'media_handle' => 'video-template-example-handle',
        'position' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertInertia(fn ($page) => $page
            ->where('selected.templates.0.name', 'video_apresentacao')
            ->where('selected.templates.0.headerType', 'VIDEO'));

    $this->actingAs($user)
        ->post(route('inbox.take', inboxConversationParams($execution)))
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));
    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), [
            'type' => 'template',
            'template' => $template->name,
        ])
        ->assertSessionHasErrors(['template' => 'O envio de Templates com cabeçalho de vídeo ainda não está disponível.']);

    Http::assertNothingSent();
});

it('rejects an unsupported outgoing media format before contacting WhatsApp', function (string $type, string $message) {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->connected()->create();
    $contact = Contact::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('inbox.messages.store', [
        'contact' => $contact,
        'channel' => $channel,
    ]), [
        'type' => $type,
        'media' => UploadedFile::fake()->create('arquivo.exe', 1, 'application/octet-stream'),
    ]);

    $response->assertSessionHasErrors(['media' => $message]);
    Http::assertNothingSent();
})->with([
    'image format' => ['image', 'A imagem deve estar no formato JPEG ou PNG.'],
    'document format' => ['document', 'O documento deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.'],
    'video format' => ['video', 'O vídeo deve estar no formato MP4 ou 3GP.'],
    'audio format' => ['audio', 'O áudio deve estar no formato OGG/Opus, MP3, AAC ou MP4.'],
]);

it('rejects outgoing media that exceeds its type size limit before contacting WhatsApp', function (string $type, UploadedFile $file, string $message) {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->connected()->create();
    $contact = Contact::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('inbox.messages.store', [
        'contact' => $contact,
        'channel' => $channel,
    ]), [
        'type' => $type,
        'media' => $file,
    ]);

    $response->assertSessionHasErrors(['media' => $message]);
    Http::assertNothingSent();
})->with([
    'image size' => ['image', UploadedFile::fake()->create('foto.jpg', 5121, 'image/jpeg'), 'A imagem deve ter no máximo 5 MB.'],
    'document size' => ['document', UploadedFile::fake()->create('orcamento.pdf', 102401, 'application/pdf'), 'O documento deve ter no máximo 100 MB.'],
    'video size' => ['video', UploadedFile::fake()->create('video.mp4', 16385, 'video/mp4'), 'O vídeo deve ter no máximo 16 MB.'],
    'audio size' => ['audio', UploadedFile::fake()->create('voz.ogg', 16385, 'audio/ogg'), 'O áudio deve ter no máximo 16 MB.'],
]);

it('pauses the Agent after a direct conversation sends media', function () {
    Storage::fake('whatsapp_media');
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create([
        'agent_id' => $agent->getKey(),
    ]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create([
        'access_token' => 'direct-media-access-token',
    ]);
    WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => 'direct-media-phone-id',
        'whatsapp_account_id' => $account->getKey(),
    ]);
    $contact = Contact::factory()->for($team)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'created_at' => now(),
    ]);
    $file = validOutgoingMedia('image');
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(['id' => 'direct-media-upload-id'])
            ->push(['messages' => [['id' => 'direct-media-message-id']]]),
    ]);

    $this->actingAs($user)
        ->post(route('inbox.messages.store', [
            'contact' => $contact,
            'channel' => $channel,
        ]), [
            'type' => 'image',
            'media' => $file,
        ])
        ->assertRedirect(route('inbox.show', [$contact, $channel]));

    $state = AgentConversationState::query()->firstOrFail();
    expect($state->is_paused)->toBeTrue()
        ->and($state->assigned_user_id)->toBe($user->getKey());
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'direct-media-message-id',
        'payload_type' => 'image',
        'delivery_status' => 'sent',
        'sent_by_user_id' => $user->getKey(),
    ]);
});

it('blocks direct media sends after the customer care window closes', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create([
        'agent_id' => $agent->getKey(),
    ]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create([
        'access_token' => 'expired-media-access-token',
    ]);
    WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => 'expired-media-phone-id',
        'whatsapp_account_id' => $account->getKey(),
    ]);
    $contact = Contact::factory()->for($team)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)
        ->post(route('inbox.messages.store', [
            'contact' => $contact,
            'channel' => $channel,
        ]), [
            'type' => 'image',
            'media' => validOutgoingMedia('image'),
        ])
        ->assertRedirect(route('inbox.show', [$contact, $channel]));

    Http::assertNothingSent();
    $this->assertDatabaseHas('messages', [
        'contact_id' => $contact->getKey(),
        'channel_id' => $channel->getKey(),
        'payload_type' => 'image',
        'delivery_status' => 'failed',
        'failure_reason' => 'customer_care_window_closed',
    ]);
});
