<?php

use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createWhatsAppPhone(Channel $channel, array $phoneAttributes = []): void
{
    WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->create($phoneAttributes);
}

it('lists no channels and every type as available to add when the Empresa has none', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/index')
        ->has('channels', 0)
        ->has('channelTypes', 3)
        ->where('channelTypes.0.value', 'whatsapp')
        ->where('channelTypes.0.configured', false)
        ->where('channelTypes.1.value', 'website_chat')
        ->where('channelTypes.1.available', false)
        ->where('channelTypes.2.value', 'instagram_direct')
        ->where('channelTypes.2.available', false),
    );
});

it('lists only the Channels the Empresa has actually configured', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create(['name' => 'Agente Comercial']);
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->create(['type' => ChannelType::WhatsApp, 'agent_id' => $agent->id]);
    createWhatsAppPhone($channel, ['display_phone_number' => '+55 11 99999-8888']);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channels', 1)
        ->where('channels.0.id', $channel->id)
        ->where('channels.0.label', 'WhatsApp')
        ->where('channels.0.destination', route('channels.whatsapp.edit'))
        ->where('channels.0.status', 'connected')
        ->where('channels.0.displayPhoneNumber', '+55 11 99999-8888')
        ->where('channels.0.agent.name', 'Agente Comercial')
        ->where('channelTypes.0.value', 'whatsapp')
        ->where('channelTypes.0.configured', true),
    );
});

it('shows the last error of a Channel in error', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->error('O access token é inválido ou expirou.')
        ->create(['type' => ChannelType::WhatsApp]);
    createWhatsAppPhone($channel);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.status', 'error')
        ->where('channels.0.statusLabel', 'Erro')
        ->where('channels.0.lastError', 'O access token é inválido ou expirou.'),
    );
});

it('lists a connected Channel without an Agent', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->withoutAgent()
        ->create(['type' => ChannelType::WhatsApp]);
    createWhatsAppPhone($channel);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.status', 'connected')
        ->where('channels.0.agent', null),
    );
});

it('does not expose another company Channel in the listing', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($otherUser->company())
        ->connected()
        ->create(['type' => ChannelType::WhatsApp]);
    createWhatsAppPhone($channel, ['display_phone_number' => '+55 21 88888-7777']);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channels', 0),
    );
});

it('lets a Member view the listing without management destinations', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $channel = Channel::factory()
        ->for($owner->company())
        ->connected()
        ->create(['type' => ChannelType::WhatsApp]);
    createWhatsAppPhone($channel);

    $response = $this->actingAs($member)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageChannels', false)
        ->where('channels.0.destination', null),
    );
});

it('rejects a non-WhatsApp channel on WhatsApp management routes', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WebsiteChat,
    ]);

    $response = $this->actingAs($user)->delete(
        route('channels.whatsapp.destroy', $channel),
    );

    $response->assertNotFound();
    $this->assertModelExists($channel);
});

it('hides the WhatsApp test type from the catalog for a regular customer outside local', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channelTypes', 3)
        ->missing('channelTypes.3'),
    );
});

it('shows the WhatsApp test type to a platform administrator', function () {
    $user = User::factory()
        ->withTeam()
        ->create(['is_platform_admin' => true]);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channelTypes', 4)
        ->where('channelTypes.3.value', 'whatsapp_test')
        ->where('channelTypes.3.available', true)
        ->where('channelTypes.3.configured', false),
    );
});

it('shows the WhatsApp test type in the local catalog', function () {
    app()->instance('env', 'local');
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channelTypes', 4)
        ->where('channelTypes.3.value', 'whatsapp_test'),
    );
});

it('uses UUIDs as Channel identifiers', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsApp,
    ]);

    expect(Str::isUuid($channel->id))->toBeTrue();
});
