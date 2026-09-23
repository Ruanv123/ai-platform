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
        ->has('crmCatalog', 6)
        ->has('ecommerceCatalog', 6),
    );
});

it('renders the complete CRM catalog for a company member', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/index')
        ->has('crmCatalog', 6)
        ->has('ecommerceCatalog', 6)
        ->where('crmCatalog.0.value', 'rd_station')
        ->where('crmCatalog.0.name', 'RD Station CRM')
        ->where('crmCatalog.0.logo', 'RD')
        ->where('crmCatalog.0.capabilities.0', 'Criar e atualizar contatos')
        ->where('crmCatalog.1.value', 'hubspot')
        ->where('crmCatalog.1.name', 'HubSpot')
        ->where('crmCatalog.2.value', 'pipedrive')
        ->where('crmCatalog.2.name', 'Pipedrive')
        ->where('crmCatalog.3.value', 'kommo')
        ->where('crmCatalog.3.name', 'Kommo')
        ->where('crmCatalog.4.value', 'salesforce')
        ->where('crmCatalog.4.name', 'Salesforce')
        ->where('crmCatalog.5.value', 'ploomes')
        ->where('crmCatalog.5.name', 'Ploomes')
        ->has('crmCatalog.0.description')
        ->has('crmCatalog.0.capabilities', 3)
        ->where('ecommerceCatalog.0.value', 'shopify')
        ->where('ecommerceCatalog.0.name', 'Shopify')
        ->where('ecommerceCatalog.1.value', 'nuvemshop')
        ->where('ecommerceCatalog.2.value', 'woocommerce')
        ->where('ecommerceCatalog.3.value', 'vtex')
        ->where('ecommerceCatalog.4.value', 'loja_integrada')
        ->where('ecommerceCatalog.5.value', 'mercado_livre')
        ->has('ecommerceCatalog.0.description')
        ->has('ecommerceCatalog.0.capabilities', 3),
    );
});

it('shows the dedicated page for a type without a configured integration yet', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.show', 'crm'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/show')
        ->where('type', 'crm')
        ->where('integration', null)
        ->has('catalog', 6),
    );
});

it('shows the current connection on the dedicated page for a configured type', function () {
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'agenda',
        'provider' => 'agenda',
    ]);

    $response = $this->actingAs($user)->get(route('integrations.show', 'agenda'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('integrations/show')
        ->where('type', 'agenda')
        ->where('integration.id', $integration->getKey()),
    );
});

it('rejects a dedicated page for a type outside the catalog', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)->get('/integrations/http')->assertMethodNotAllowed();
});
