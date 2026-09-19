<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppTestConnection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

function whatsappTestDetails(array $overrides = []): array
{
    return array_merge([
        'service_url' => 'http://evolution.test:8080',
        'instance_name' => 'local-development',
        'access_token' => 'evolution-secret',
        'agent_id' => null,
    ], $overrides);
}

it('saves a WhatsApp test connection and marks its channel as connected', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        whatsappTestDetails(),
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit'));
    $channel = Channel::query()
        ->whereBelongsTo($user->company())
        ->where('type', ChannelType::WhatsAppTest)
        ->firstOrFail();
    $connection = $channel->whatsappTestConnection;

    expect($channel->status)->toBe(ChannelStatus::Connected)
        ->and($connection->service_url)->toBe('http://evolution.test:8080')
        ->and($connection->instance_name)->toBe('local-development')
        ->and($connection->access_token)->toBe('evolution-secret');
    $this->assertDatabaseMissing('whatsapp_test_connections', [
        'access_token' => 'evolution-secret',
    ]);
});

it('renders a saved WhatsApp test connection without exposing its secret', function () {
    app()->instance('env', 'local');
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->create([
            'type' => ChannelType::WhatsAppTest,
            'status' => ChannelStatus::Connected,
        ]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'service_url' => 'http://evolution.test:8080',
        'instance_name' => 'local-development',
        'access_token' => 'evolution-secret',
    ]);

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp-test')
        ->where('whatsappTest.id', $channel->id)
        ->where('whatsappTest.serviceUrl', 'http://evolution.test:8080')
        ->where('whatsappTest.instanceName', 'local-development')
        ->where('whatsappTest.accessTokenSet', true)
        ->where('whatsappTest.accessTokenMasked', '••••••••')
        ->missing('whatsappTest.access_token')
    );
});

it('allows a company to keep official and test WhatsApp channels together', function () {
    app()->instance('env', 'local');
    $this->withoutMiddleware(PreventRequestForgery::class);
    $user = User::factory()->withTeam()->create();
    $officialChannel = Channel::factory()
        ->for($user->company())
        ->create(['type' => ChannelType::WhatsApp]);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        whatsappTestDetails(),
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit'));
    expect($user->company()->channels()->count())->toBe(2)
        ->and($officialChannel->fresh()->type)->toBe(ChannelType::WhatsApp)
        ->and($user->company()->channels()->where('type', ChannelType::WhatsAppTest)->exists())->toBeTrue();
});

it('forbids a regular customer from configuring the test channel outside local', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.store'),
        whatsappTestDetails(),
    );

    $response->assertForbidden();
    $this->assertDatabaseCount('whatsapp_test_connections', 0);
});
