<?php

use App\Enums\TeamRole;
use App\Models\Collection;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login when they visit collections', function () {
    $response = $this->get(route('collections.index'));

    $response->assertRedirect(route('login'));
});

it('renders only the current company collections with document counts', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $defaultCollection = Collection::factory()->for($team)->default()->create();
    $collection = Collection::factory()->for($team)->create([
        'name' => 'Vendas',
        'description' => 'Materiais comerciais',
    ]);
    $otherCollection = Collection::factory()->create(['name' => 'Privada']);

    $response = $this->actingAs($user)->get(route('collections.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('knowledge/collections')
        ->where('permissions.canManageKnowledge', true)
        ->has('collections', 2)
        ->where('collections.0.id', $defaultCollection->id)
        ->where('collections.0.name', 'Geral')
        ->where('collections.0.documentsCount', 0)
        ->where('collections.1.id', $collection->id)
        ->where('collections.1.name', 'Vendas')
        ->where('collections.1.description', 'Materiais comerciais')
        ->where('collections.1.documentsCount', 0),
    );

    expect($otherCollection->team_id)->not->toBe($team->id);
});

it('allows an owner to create a collection for the current company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();

    $response = $this->actingAs($user)->post(route('collections.store'), [
        'name' => 'Suporte',
        'description' => 'Procedimentos de suporte',
        'team_id' => Team::factory()->create()->id,
    ]);

    $response->assertRedirect(route('collections.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Coleção criada com sucesso.',
    ]);
    $this->assertDatabaseHas('collections', [
        'team_id' => $team->id,
        'name' => 'Suporte',
        'description' => 'Procedimentos de suporte',
        'is_default' => false,
    ]);
});

it('allows an admin to update a collection', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $collection = Collection::factory()->for($team)->create(['name' => 'Antigo']);

    $response = $this->actingAs($admin)->patch(
        route('collections.update', $collection),
        ['name' => 'Atualizado', 'description' => 'Descrição nova'],
    );

    $response->assertRedirect(route('collections.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Coleção atualizada com sucesso.',
    ]);
    $this->assertDatabaseHas('collections', [
        'id' => $collection->id,
        'name' => 'Atualizado',
        'description' => 'Descrição nova',
    ]);
});

it('allows a member to view collections without management permissions', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    Collection::factory()->for($team)->default()->create();
    $collection = Collection::factory()->for($team)->create(['name' => 'Base']);

    $response = $this->actingAs($member)->get(route('collections.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageKnowledge', false)
        ->where('collections.1.name', 'Base'),
    );
    $this->assertModelExists($collection);
});

it('forbids a member from creating collections', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this->actingAs($member)->post(route('collections.store'), [
        'name' => 'Nova',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('collections', ['name' => 'Nova']);
});

it('forbids a member from updating collections', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $collection = Collection::factory()->for($team)->create(['name' => 'Base']);

    $response = $this->actingAs($member)->patch(
        route('collections.update', $collection),
        ['name' => 'Alterada'],
    );

    $response->assertForbidden();
    $this->assertDatabaseHas('collections', [
        'id' => $collection->id,
        'name' => 'Base',
    ]);
});

it('forbids a member from deleting collections', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $collection = Collection::factory()->for($team)->create(['name' => 'Base']);

    $response = $this->actingAs($member)->delete(
        route('collections.destroy', $collection),
    );

    $response->assertForbidden();
    $this->assertModelExists($collection);
});

it('deletes a non-default collection and returns success feedback', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $collection = Collection::factory()->for($team)->create(['name' => 'Temporária']);

    $response = $this->actingAs($user)->delete(
        route('collections.destroy', $collection),
    );

    $response->assertRedirect(route('collections.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Coleção excluída com sucesso.',
    ]);
    $this->assertModelMissing($collection);
});

it('forbids deleting the default collection', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $defaultCollection = Collection::factory()->for($team)->default()->create();

    $response = $this->actingAs($user)->delete(
        route('collections.destroy', $defaultCollection),
    );

    $response->assertForbidden();
    $this->assertModelExists($defaultCollection);
});

it('returns not found when a company accesses another company collection', function () {
    $owner = User::factory()->withTeam()->create();
    $otherCollection = Collection::factory()->create(['name' => 'Isolada']);

    $response = $this->actingAs($owner)->patch(
        route('collections.update', $otherCollection),
        ['name' => 'Tentativa'],
    );

    $response->assertNotFound();
    $this->assertDatabaseHas('collections', [
        'id' => $otherCollection->id,
        'name' => 'Isolada',
    ]);
});

it('returns clear validation messages for invalid collection data', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('collections.store'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors([
        'name' => 'Informe o nome da Coleção.',
    ]);
    $this->assertDatabaseMissing('collections', ['name' => '']);
});
