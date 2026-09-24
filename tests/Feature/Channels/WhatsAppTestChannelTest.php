<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppTestConnection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function fakeEvolutionGo(): void
{
    config([
        'services.evolution.base_url' => 'https://evolution.test',
        'services.evolution.api_key' => 'admin-key',
        'services.evolution.webhook_url' => null,
    ]);

    Http::fake([
        'evolution.test/instance/create' => Http::response(['message' => 'success']),
        'evolution.test/instance/connect' => Http::response(['message' => 'success']),
    ]);
}

it('creates the instance on Evolution Go and stores the Channel as not configured', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    fakeEvolutionGo();
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        ['name' => 'local-development', 'agent_id' => null],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit'));
    $channel = Channel::query()
        ->whereBelongsTo($user->company())
        ->where('type', ChannelType::WhatsAppTest)
        ->firstOrFail();
    $connection = $channel->whatsappTestConnection;
    $team = $user->company();
    $instanceToken = $connection->access_token;

    expect($channel->status)->toBe(ChannelStatus::NotConfigured)
        ->and($connection->service_url)->toBe('https://evolution.test')
        ->and($connection->instance_name)->toBe('canal_'.$team->getKey())
        ->and($connection->name)->toBe('local-development')
        ->and($instanceToken)->not->toBe('')
        ->and($connection->qrcode)->toBeNull();
    $this->assertDatabaseMissing('whatsapp_test_connections', [
        'access_token' => $instanceToken,
    ]);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://evolution.test/instance/create'
        && $request['name'] === 'canal_'.$team->getKey()
        && $request['token'] === $instanceToken
        && $request->header('apikey') === ['admin-key']);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://evolution.test/instance/connect'
        && $request->header('apikey') === [$instanceToken]
        && $request['webhookUrl'] === route('webhooks.whatsapp-test'));
});

it('registers the configured webhook URL when the app is not reachable through its own URL', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    fakeEvolutionGo();
    config(['services.evolution.webhook_url' => 'http://host.docker.internal:8000/webhooks/whatsapp-test']);
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        ['name' => 'local-development', 'agent_id' => null],
    )->assertRedirect(route('channels.whatsapp-test.edit'));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://evolution.test/instance/connect'
        && $request['webhookUrl'] === 'http://host.docker.internal:8000/webhooks/whatsapp-test');
});

it('does not create a Channel when Evolution Go refuses the instance', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    config([
        'services.evolution.base_url' => 'https://evolution.test',
        'services.evolution.api_key' => 'admin-key',
    ]);
    Http::fake([
        'evolution.test/instance/create' => Http::response(['error' => 'instance already exists'], 400),
    ]);
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        ['name' => 'local-development', 'agent_id' => null],
    );

    $response->assertSessionHasErrors(['name' => 'O Evolution Go recusou a operação: instance already exists']);
    $this->assertDatabaseCount('whatsapp_test_connections', 0);
    expect($user->company()->channels()->where('type', ChannelType::WhatsAppTest)->exists())->toBeFalse();
});

it('fetches the QR code live from Evolution Go instead of the database and without exposing the secret', function () {
    app()->instance('env', 'local');
    fakeEvolutionGo();
    $qrcode = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nlive");
    Http::fake([
        'evolution.test/instance/status' => Http::response(['data' => ['Connected' => true, 'LoggedIn' => false]]),
        'evolution.test/instance/qr' => Http::response(['data' => ['Qrcode' => $qrcode, 'Code' => '2@abc']]),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'canal_'.$user->company()->getKey(),
        'name' => 'local-development',
        'access_token' => 'evolution-secret',
        'qrcode' => 'data:image/png;base64,stale',
    ]);

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp-test')
        ->where('whatsappTest.id', $channel->id)
        ->where('whatsappTest.name', 'local-development')
        ->where('whatsappTest.qrcode', $qrcode)
        ->missing('whatsappTest.accessToken')
        ->missing('whatsappTest.access_token')
    );
    Http::assertSent(fn ($request): bool => $request->url() === 'https://evolution.test/instance/qr'
        && $request->method() === 'GET'
        && $request->header('apikey') === ['evolution-secret']);
});

it('renders without a QR code when Evolution Go fails or returns no image', function (callable $fake) {
    app()->instance('env', 'local');
    fakeEvolutionGo();
    Http::fake([
        'evolution.test/instance/status' => Http::response(['data' => ['LoggedIn' => false]]),
        'evolution.test/instance/qr' => $fake(),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create();

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('whatsappTest.qrcode', null)
    );
})->with([
    'server error' => [fn () => Http::response(['error' => 'boom'], 500)],
    'not an image' => [fn () => Http::response(['data' => ['Qrcode' => base64_encode('not an image')]])],
    'empty' => [fn () => Http::response(['data' => []])],
]);

it('marks the Channel as connected without requesting a QR code when the session is already logged in', function () {
    app()->instance('env', 'local');
    fakeEvolutionGo();
    Http::fake([
        'evolution.test/instance/status' => Http::response(['data' => ['Connected' => true, 'LoggedIn' => true]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsAppTest, 'status' => ChannelStatus::NotConfigured]);
    WhatsAppTestConnection::factory()->for($channel)->create();

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('whatsappTest.qrcode', null));
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected);
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/instance/qr'));
});

it('does not ask Evolution Go for a QR code once the Channel is connected', function () {
    app()->instance('env', 'local');
    fakeEvolutionGo();
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsAppTest, 'status' => ChannelStatus::Connected]);
    WhatsAppTestConnection::factory()->for($channel)->create();

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('whatsappTest.qrcode', null));
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/instance/qr'));
});

it('only lets updating the linked Agent after the Channel exists', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create();

    $response = $this->actingAs($user)->patch(
        route('channels.whatsapp-test.update', $channel),
        ['agent_id' => $agent->id],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit'));
    expect($channel->fresh()->agent_id)->toBe($agent->id);
});

it('allows a company to keep official and test WhatsApp channels together', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    fakeEvolutionGo();
    $user = User::factory()->withTeam()->create();
    $officialChannel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsApp]);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        ['name' => 'local-development', 'agent_id' => null],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit'));
    expect($user->company()->channels()->count())->toBe(2)
        ->and($officialChannel->fresh()->type)->toBe(ChannelType::WhatsApp)
        ->and($user->company()->channels()->where('type', ChannelType::WhatsAppTest)->exists())->toBeTrue();
});

it('forbids a regular customer from configuring the test channel outside local', function () {
    fakeEvolutionGo();
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        ['name' => 'local-development', 'agent_id' => null],
    );

    $response->assertForbidden();
    $this->assertDatabaseCount('whatsapp_test_connections', 0);
});

it('rejects a second WhatsApp test channel for the same Empresa', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    fakeEvolutionGo();
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        ['name' => 'another-one', 'agent_id' => null],
    );

    $response->assertSessionHasErrors('name');
    $this->assertDatabaseCount('whatsapp_test_connections', 1);
});
