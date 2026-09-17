<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

test('team member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    $response = $this
        ->actingAs($owner)
        ->patch(route('team.members.update', $member), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('team.edit'));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);
});

test('team member roles can be updated by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $admin->switchTeam($team);

    $response = $this
        ->actingAs($admin)
        ->patch(route('team.members.update', $member), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('team.edit'));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);
});

test('team member roles cannot be updated by members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Member->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $admin->switchTeam($team);

    $response = $this
        ->actingAs($admin)
        ->patch(route('team.members.update', $member), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('team owner role cannot be changed', function () {
    $owner = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($owner)
        ->patch(route('team.members.update', $owner), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();
    expect($owner->fresh()->teamRole($owner->company()))->toBe(TeamRole::Owner);
});

test('team members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    $response = $this
        ->actingAs($owner)
        ->delete(route('team.members.destroy', $member));

    $response->assertRedirect(route('team.edit'));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team members can be removed by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $admin->switchTeam($team);

    $response = $this
        ->actingAs($admin)
        ->delete(route('team.members.destroy', $member));

    $response->assertRedirect(route('team.edit'));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team members cannot be removed by members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Member->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $admin->switchTeam($team);

    $response = $this
        ->actingAs($admin)
        ->delete(route('team.members.destroy', $member));

    $response->assertForbidden();
});

test('team owner cannot be removed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $response = $this
        ->actingAs($owner)
        ->delete(route('team.members.destroy', $owner));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('team member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    $response = $this
        ->actingAs($owner)
        ->patch(route('team.members.update', $member), [
            'role' => TeamRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Member->value);
});

test('removed member no longer has a current team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('team.members.destroy', $member));

    expect($member->fresh()->current_team_id)->toBeNull();
});

test('team members can leave their company', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->delete(route('team.leave'));

    $response->assertRedirect(route('onboarding.company'));
    expect($member->fresh()->company())->toBeNull();
    expect($member->fresh()->current_team_id)->toBeNull();
});

test('team owners cannot leave their company', function () {
    $owner = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($owner)
        ->delete(route('team.leave'));

    $response->assertForbidden();
    expect($owner->fresh()->company())->not->toBeNull();
});
