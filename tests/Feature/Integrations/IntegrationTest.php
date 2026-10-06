<?php

use App\Enums\TeamRole;
use App\Models\Integration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login when they visit integrations', function () {
    $response = $this->get(route('integrations.index'));

    $response->assertRedirect(route('login'));
});

it('allows a Member to view the integrations catalog', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());

    $response = $this->actingAs($member)->get(route('integrations.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/index')
        ->has('agendaCatalog', 1)
        ->where('agenda', null),
    );
});

it('shows the dedicated page for a type without a configured integration yet', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.show', 'agenda'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/show')
        ->where('integration', null),
    );
});

it('shows the current connection on the dedicated page for a configured type', function () {
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'agenda',
        'provider' => 'google',
    ]);

    $response = $this->actingAs($user)->get(route('integrations.show', 'agenda'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/show')
        ->where('integration.id', $integration->getKey()),
    );
});

it('rejects a dedicated page for a type outside the catalog', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)->get('/integrations/crm')->assertMethodNotAllowed();
});
