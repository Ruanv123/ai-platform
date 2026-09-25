<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\Oficial\MetaEventProcessor;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\WhatsAppTemplateService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

function createMetaHealthChannel(): array
{
    $account = WhatsAppAccount::factory()->create();
    $channel = Channel::factory()->for($account->team)->create([
        'type' => ChannelType::WhatsApp,
        'status' => ChannelStatus::Connected,
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create([
            'phone_number_id' => '15551234567890',
            'display_phone_number' => '+55 11 99999-0000',
            'verified_name' => 'Empresa Atual',
        ]);

    return [$channel, $phoneNumber, $account];
}

function postAndProcessMetaHealthPayload(TestCase $testCase, WhatsAppAccount $account, array $payload): void
{
    $payload['entry'][0]['id'] = $account->waba_id;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, $account->app_secret);
    Queue::fake([ProcessWhatsAppWebhook::class]);

    $response = $testCase->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $jobs = Queue::pushed(ProcessWhatsAppWebhook::class);
    expect($jobs)->toHaveCount(1);
    $jobs->first()->handle(app(MetaEventProcessor::class));
}

function postAndProcessMetaHealthFixture(TestCase $testCase, WhatsAppAccount $account, string $fixture): void
{
    $payload = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/whatsapp/oficial/'.$fixture)),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    postAndProcessMetaHealthPayload($testCase, $account, $payload);
}

it('marks the channel unavailable and stores the Meta ban state from an account update fixture', function () {
    [$channel, , $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'account-update-disabled.json');

    expect($account->fresh()->ban_state)->toBe('FLAGGED')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('A Conta do WhatsApp foi bloqueada ou restringida pela Meta.');
});

it('marks the channel unavailable when Meta removes the platform partner', function () {
    [$channel, , $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'account-update-partner-removed.json');

    expect($account->fresh()->ban_state)->toBe('PARTNER_REMOVED')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toContain('removida como parceira');
});

it('stores account restrictions and marks the channel unavailable', function () {
    [$channel, , $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'account-update-restriction.json');

    expect($account->fresh()->ban_state)->toBe('ACCOUNT_RESTRICTION')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Error);
});

it('restores the channel after Meta reinstates a restricted account', function () {
    [$channel, , $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'account-update-disabled.json');
    postAndProcessMetaHealthFixture($this, $account, 'account-update-reinstate.json');

    expect($account->fresh()->ban_state)->toBe('REINSTATE')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBeNull();
});

it('stores a scheduled disable without marking the channel unavailable early', function () {
    [$channel, , $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'account-update-scheduled-disable.json');

    expect($account->fresh()->ban_state)->toBe('SCHEDULE_FOR_DISABLE')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Connected);
});

it('stores account review decisions from the Meta webhook fixture', function () {
    [$channel, , $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'account-review-update-approved.json');

    expect($account->fresh()->review_status)->toBe('APPROVED')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Connected);
});

it('stores the phone quality and messaging limit from a Meta quality update fixture', function () {
    [, $phoneNumber, $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'phone-number-quality-update-flagged.json');

    expect($phoneNumber->fresh()->quality_rating)->toBe('RED')
        ->and($phoneNumber->fresh()->messaging_limit_tier)->toBe('TIER_1K');
});

it('stores the approved display name and name status from a Meta name update fixture', function () {
    [, $phoneNumber, $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'phone-number-name-update-approved.json');

    expect($phoneNumber->fresh()->verified_name)->toBe('Clínica Exemplo')
        ->and($phoneNumber->fresh()->name_status)->toBe('APPROVED');
});

it('keeps the approved name when Meta rejects a later display name request', function () {
    [, $phoneNumber, $account] = createMetaHealthChannel();

    postAndProcessMetaHealthFixture($this, $account, 'phone-number-name-update-rejected.json');

    expect($phoneNumber->fresh()->verified_name)->toBe('Empresa Atual')
        ->and($phoneNumber->fresh()->name_status)->toBe('REJECTED');
});

it('marks the official channel when a send receives an invalid access token error', function () {
    [$channel] = createMetaHealthChannel();
    $contact = Contact::factory()->for($channel->team)->create(['phone' => '5511999999999']);
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'provider_message_id' => 'health-incoming-message',
        'content' => 'Olá',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);
    $adapter = app(WhatsAppOutputAdapterFactory::class)->make($channel);

    expect(fn () => $adapter->sendText($channel, $contact, 'Resposta'))
        ->toThrow(RequestException::class);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('O access token é inválido ou expirou.');
});

it('marks the official channel when a template send receives an invalid access token error', function () {
    [$channel] = createMetaHealthChannel();
    $contact = Contact::factory()->for($channel->team)->create(['phone' => '5511999999999']);
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);
    $adapter = app(WhatsAppOutputAdapterFactory::class)->make($channel);

    expect(fn () => $adapter->sendTemplate($channel, $contact, 'lembrete_consulta'))
        ->toThrow(RequestException::class);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('O access token é inválido ou expirou.');
});

it('marks the official channel when submitting a template receives an invalid access token error', function () {
    [$channel, , $account] = createMetaHealthChannel();
    $template = WhatsAppTemplate::factory()
        ->for($account, 'account')
        ->draft()
        ->create();
    $templateEndpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$account->waba_id.'/message_templates';
    Http::preventStrayRequests();
    Http::fake([
        $templateEndpoint => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);

    expect(fn () => app(WhatsAppTemplateService::class)->submit($template))
        ->toThrow(RequestException::class);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('O access token é inválido ou expirou.');
});

it('marks the official channel when template synchronization receives an invalid access token error', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create();
    WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);

    $this->actingAs($user)->post(route('templates.sync'))->assertRedirect(route('templates.index'));

    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('O access token é inválido ou expirou.');
});
