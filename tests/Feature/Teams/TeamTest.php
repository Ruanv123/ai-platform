<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the team settings page renders the authenticated users current team', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/edit')
            ->where('team.id', $user->currentTeam->id)
            ->where('members.0.role', TeamRole::Owner->value)
            ->where('members.0.role_label', TeamRole::Owner->label()),
        );
});

test('team settings use the current team instead of a team route parameter', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create(['name' => 'Other Team']);
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($otherTeam);

    $response = $this
        ->actingAs($user)
        ->patch(route('teams.update'), [
            'name' => 'Updated Current Team',
        ]);

    $response->assertRedirect(route('teams.edit'));

    $this->assertDatabaseHas('teams', [
        'id' => $otherTeam->id,
        'name' => 'Updated Current Team',
    ]);
});

test('team settings cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->patch(route('teams.update'), [
            'name' => 'Updated Name',
        ]);

    $response->assertForbidden();
});

test('team creation and switching routes are not registered', function () {
    expect(Route::has('teams.store'))->toBeFalse();
    expect(Route::has('teams.switch'))->toBeFalse();
    expect(Route::has('teams.index'))->toBeFalse();
});

test('team deletion route is not registered', function () {
    expect(Route::has('teams.destroy'))->toBeFalse();
});

test('guests cannot access current team settings', function () {
    $response = $this->get(route('teams.edit'));

    $response->assertRedirect(route('login'));
});
