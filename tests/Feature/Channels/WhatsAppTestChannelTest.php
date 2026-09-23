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

it('renders a saved WhatsApp test connection with its QR code and without exposing its secret', function () {
    app()->instance('env', 'local');
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'canal_'.$user->company()->getKey(),
        'name' => 'local-development',
        'access_token' => 'evolution-secret',
        'qrcode' => 'data:image/png;base64,abc123',
    ]);

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp-test')
        ->where('whatsappTest.id', $channel->id)
        ->where('whatsappTest.name', 'local-development')
        ->where('whatsappTest.qrcode', 'data:image/png;base64,abc123')
        ->missing('whatsappTest.accessToken')
        ->missing('whatsappTest.access_token')
    );
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
