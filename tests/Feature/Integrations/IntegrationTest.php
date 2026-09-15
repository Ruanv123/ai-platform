<?php

use App\Enums\TeamRole;
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
        ->has('integrations', 6),
    );
});

it('renders the complete CRM catalog for a company member', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/index')
        ->has('integrations', 6)
        ->where('integrations.0.value', 'rd_station')
        ->where('integrations.0.name', 'RD Station CRM')
        ->where('integrations.0.logo', 'RD')
        ->where('integrations.0.capabilities.0', 'Criar e atualizar contatos')
        ->where('integrations.1.value', 'hubspot')
        ->where('integrations.1.name', 'HubSpot')
        ->where('integrations.2.value', 'pipedrive')
        ->where('integrations.2.name', 'Pipedrive')
        ->where('integrations.3.value', 'kommo')
        ->where('integrations.3.name', 'Kommo')
        ->where('integrations.4.value', 'salesforce')
        ->where('integrations.4.name', 'Salesforce')
        ->where('integrations.5.value', 'ploomes')
        ->where('integrations.5.name', 'Ploomes')
        ->has('integrations.0.description')
        ->has('integrations.0.capabilities', 3),
    );
});
