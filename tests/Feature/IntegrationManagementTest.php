<?php

use App\Models\Flow;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('stores credentials encrypted and never returns them to the page', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->postJson(route('integrations.store'), [
        'name' => 'Agenda principal',
        'type' => 'agenda',
        'provider' => 'agenda',
        'base_url' => 'https://agenda.example.test',
        'auth_type' => 'bearer',
        'token' => 'secret-token',
    ]);

    $response->assertCreated()->assertJsonMissingPath('credentials');
    $integration = Integration::withoutGlobalScopes()->firstOrFail();

    expect($integration->credentials['token'])->toBe('secret-token')
        ->and($integration->getRawOriginal('credentials'))->not->toContain('secret-token');
});

it('tests a saved connection and records its state', function () {
    Http::preventStrayRequests();
    Http::fake(['https://agenda.example.test/*' => Http::response(['ok' => true])]);
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'agenda',
        'credentials' => ['base_url' => 'https://agenda.example.test', 'auth_type' => 'none'],
    ]);

    $this->actingAs($user)
        ->postJson(route('integrations.test', $integration))
        ->assertOk()
        ->assertJsonPath('integration.status', 'connected');

    expect($integration->fresh()->status)->toBe('connected');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://agenda.example.test/');
});

it('isolates integrations between companies', function () {
    $owner = User::factory()->withTeam()->create();
    $other = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($owner->company())->create();

    $this->actingAs($other)->postJson(route('integrations.test', $integration))->assertNotFound();
});

it('explains when an integration is still used by a flow instead of deleting it', function () {
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create();
    Flow::factory()->for($user->company())->create([
        'draft_dsl' => [
            'version' => 1,
            'nodes' => [['id' => 'request', 'type' => 'http_request', 'config' => ['integration_id' => $integration->getKey()]]],
            'edges' => [],
        ],
    ]);

    $this->actingAs($user)
        ->delete(route('integrations.destroy', $integration))
        ->assertSessionHasErrors('integration');

    $this->assertModelExists($integration);
});
