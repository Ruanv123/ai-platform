<?php

use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Inertia\Testing\AssertableInertia as Assert;

function createCatalogWhatsAppPhone(Channel $channel, array $phoneAttributes = []): void
{
    WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->create($phoneAttributes);
}

it('renders all channel types and the configured WhatsApp state', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create(['name' => 'Agente Comercial']);
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->create(['type' => ChannelType::WhatsApp, 'agent_id' => $agent->id]);
    createCatalogWhatsAppPhone($channel, ['display_phone_number' => '+55 11 99999-8888']);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/index')
        ->has('channels', 3)
        ->where('channels.0.value', 'whatsapp')
        ->where('channels.0.label', 'WhatsApp')
        ->where('channels.0.available', true)
        ->where('channels.0.destination', route('channels.whatsapp.edit'))
        ->where('channels.0.status', 'connected')
        ->where('channels.0.displayPhoneNumber', '+55 11 99999-8888')
        ->where('channels.0.agent.name', 'Agente Comercial')
        ->where('channels.1.value', 'website_chat')
        ->where('channels.1.label', 'Chat no site')
        ->where('channels.1.available', false)
        ->where('channels.1.destination', null)
        ->where('channels.2.value', 'instagram_direct')
        ->where('channels.2.label', 'Instagram Direct')
        ->where('channels.2.available', false)
        ->where('channels.2.destination', null),
    );
});

it('renders the latest WhatsApp error in the catalog state', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->error('O access token é inválido ou expirou.')
        ->create(['type' => ChannelType::WhatsApp]);
    createCatalogWhatsAppPhone($channel);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.status', 'error')
        ->where('channels.0.statusLabel', 'Erro')
        ->where('channels.0.lastError', 'O access token é inválido ou expirou.'),
    );
});

it('highlights a connected WhatsApp channel without an Agent', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->withoutAgent()
        ->create(['type' => ChannelType::WhatsApp]);
    createCatalogWhatsAppPhone($channel);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.status', 'connected')
        ->where('channels.0.agent', null)
        ->where('channels.0.agentMissing', true),
    );
});

it('does not expose another company channel in the catalog', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($otherUser->company())
        ->connected()
        ->create(['type' => ChannelType::WhatsApp]);
    createCatalogWhatsAppPhone($channel, ['display_phone_number' => '+55 21 88888-7777']);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.status', 'not_configured')
        ->where('channels.0.displayPhoneNumber', null)
        ->where('channels.0.agent', null),
    );
});

it('lets a Member view the catalog without management destinations', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $channel = Channel::factory()
        ->for($owner->company())
        ->connected()
        ->create(['type' => ChannelType::WhatsApp]);
    createCatalogWhatsAppPhone($channel);

    $response = $this->actingAs($member)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageChannels', false)
        ->where('channels.0.destination', null)
        ->where('channels.1.destination', null)
        ->where('channels.2.destination', null),
    );
});

it('exposes the readiness milestones of a configured WhatsApp channel', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->withoutAgent()
        ->create(['type' => ChannelType::WhatsApp]);
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->create([
            'display_phone_number' => '+55 11 99999-8888',
            'verified_name' => 'Ótica Vega',
            'quality_rating' => 'GREEN',
        ]);
    WhatsAppAccount::factory()->create([
        'whatsapp_phone_number_id' => $phoneNumber->id,
        'webhook_verified_at' => now()->subDay(),
        'last_tested_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.verifiedName', 'Ótica Vega')
        ->where('channels.0.qualityRating', 'GREEN')
        ->whereNot('channels.0.credentialsSavedAt', null)
        ->whereNot('channels.0.webhookVerifiedAt', null)
        ->whereNot('channels.0.lastTestedAt', null),
    );
});

it('leaves the readiness milestones empty when no WhatsApp account exists', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('channels.0.status', 'not_configured')
        ->where('channels.0.credentialsSavedAt', null)
        ->where('channels.0.webhookVerifiedAt', null)
        ->where('channels.0.lastTestedAt', null)
        ->where('channels.0.qualityRating', null),
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

it('hides the WhatsApp test channel from a regular customer outside local', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channels', 3)
        ->missing('channels.3')
    );
});

it('shows the WhatsApp test channel to a platform administrator', function () {
    $user = User::factory()
        ->withTeam()
        ->create(['is_platform_admin' => true]);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channels', 4)
        ->where('channels.3.value', 'whatsapp_test')
        ->where('channels.3.label', 'WhatsApp de testes')
        ->where('channels.3.available', true)
        ->where('channels.3.destination', route('channels.whatsapp-test.edit'))
    );
});

it('shows the WhatsApp test channel in the local catalog', function () {
    app()->instance('env', 'local');
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('channels', 4)
        ->where('channels.3.value', 'whatsapp_test')
        ->where('channels.3.destination', route('channels.whatsapp-test.edit'))
    );
});
