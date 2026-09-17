<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the team settings page renders only team management props', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('team.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/team')
            ->where('members.0.role', TeamRole::Owner->value)
            ->where('members.0.role_label', TeamRole::Owner->label())
            ->where('permissions.canDeleteTeam', false)
            ->where('canLeave', false)
            ->has('invitations')
            ->missing('team')
            ->missing('segments')
            ->missing('timezones')
            ->missing('weekdays'),
        );
});

test('team settings only list pending invitations', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
    ]);
    TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
    ]);
    $pendingInvitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('team.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('invitations', 1)
        ->where('invitations.0.code', $pendingInvitation->code),
    );
});

test('members see team members without invitations or management actions', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->get(route('team.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/team')
        ->has('members', 2)
        ->where('permissions.canUpdateMember', false)
        ->where('permissions.canRemoveMember', false)
        ->where('permissions.canCreateInvitation', false)
        ->where('permissions.canCancelInvitation', false)
        ->where('canLeave', true)
        ->missing('invitations'),
    );
});

test('the legacy team settings route is not registered and returns 404', function () {
    expect(Route::has('teams.edit'))->toBeFalse();
    expect(Route::has('teams.update'))->toBeFalse();

    $this->get('/settings/teams')->assertNotFound();
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
    $response = $this->get(route('team.edit'));

    $response->assertRedirect(route('login'));
});
