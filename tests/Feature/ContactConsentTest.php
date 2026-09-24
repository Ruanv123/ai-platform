<?php

use App\Consent\ContactConsentService;
use App\Enums\ConsentAction;
use App\Enums\ConsentPurpose;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\User;

it('records an append-only decision and exposes the latest state by purpose', function () {
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $service = app(ContactConsentService::class);

    $service->grant($contact, ConsentPurpose::Marketing, 'form');
    $service->revoke($contact, ConsentPurpose::Marketing, 'keyword', ['keyword' => 'SAIR']);
    $service->grant($contact, ConsentPurpose::Utility, 'form');

    expect($service->isGranted($contact, ConsentPurpose::Marketing))->toBeFalse()
        ->and($service->isGranted($contact, ConsentPurpose::Utility))->toBeTrue()
        ->and($service->history($contact))->toHaveCount(3)
        ->and($service->snapshot($contact)['marketing']['source'])->toBe('keyword')
        ->and($service->snapshot($contact)['utility']['granted'])->toBeTrue();

    expect(fn () => $service->history($contact)->firstOrFail()->update(['source' => 'changed']))
        ->toThrow(LogicException::class);
});

it('stores consent changes through the contact endpoint', function () {
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->create();
    $conversation = ['contact' => $contact, 'channel' => $channel];

    $response = $this->actingAs($user)->post(route('inbox.consents.store', $conversation), [
        'purpose' => ConsentPurpose::Utility->value,
        'action' => ConsentAction::Granted->value,
        'source' => 'manual',
    ]);

    $response->assertRedirect(route('inbox.show', $conversation));
    $this->assertDatabaseHas('contact_consents', [
        'team_id' => $user->company()->id,
        'contact_id' => $contact->id,
        'purpose' => 'utility',
        'action' => 'granted',
        'source' => 'manual',
    ]);
});

it('does not reveal another team contact through consent changes', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($otherUser->company())->create();
    $channel = Channel::factory()->for($otherUser->company())->create();

    $this->actingAs($user)->post(route('inbox.consents.store', ['contact' => $contact, 'channel' => $channel]), [
        'purpose' => 'marketing',
        'action' => 'granted',
    ])->assertNotFound();
});
