<?php

use App\Models\Contact;
use App\Models\Integration;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\Team;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

it('links a Contact created after its store customer and relinks on phone changes', function () {
    $team = Team::factory()->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create([
        'country_code' => 'BR',
    ]);
    $firstCustomer = StoreCustomer::factory()->for($team)->for($shop)->create([
        'first_name' => 'Mariana',
        'phone_e164' => '+5511987654321',
    ]);
    $secondCustomer = StoreCustomer::factory()->for($team)->for($shop)->create([
        'first_name' => 'Rafael',
        'phone_e164' => '+5521998765432',
    ]);

    $contact = Contact::factory()->for($team)->create([
        'name' => 'Nome no atendimento',
        'phone' => '+55 (11) 98765-4321',
    ]);

    expect($firstCustomer->fresh()->contact_id)->toBe($contact->getKey())
        ->and($secondCustomer->fresh()->contact_id)->toBeNull();

    $contact->update(['phone' => '+55 (21) 99876-5432']);

    expect($firstCustomer->fresh()->contact_id)->toBeNull()
        ->and($secondCustomer->fresh()->contact_id)->toBe($contact->getKey())
        ->and($contact->fresh()->name)->toBe('Nome no atendimento');
});

it('keeps the Contact unlinked when no same-team store customer has that phone', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);
    $otherIntegration = Integration::factory()->for($otherTeam)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create([
        'country_code' => 'BR',
    ]);
    $otherShop = Shop::factory()->for($otherTeam)->for($otherIntegration)->create([
        'country_code' => 'BR',
    ]);
    $otherCustomer = StoreCustomer::factory()->for($otherTeam)->for($otherShop)->create([
        'phone_e164' => '+5511987654321',
    ]);

    $contact = Contact::factory()->for($team)->create([
        'phone' => '+55 (11) 98765-4321',
    ]);

    expect($contact->storeCustomer()->exists())->toBeFalse()
        ->and($otherCustomer->fresh()->contact_id)->toBeNull()
        ->and($shop->team_id)->not->toBe($otherShop->team_id);
});
