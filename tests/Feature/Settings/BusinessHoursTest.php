<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function businessHoursPayload(): array
{
    return [
        'business_hours' => [
            'monday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
            'tuesday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
            'wednesday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
            'thursday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
            'friday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
            'saturday' => ['enabled' => '0', 'open' => null, 'close' => null],
            'sunday' => ['enabled' => '0', 'open' => null, 'close' => null],
        ],
        'timezone' => 'America/Sao_Paulo',
    ];
}

test('owners can render only business hours props in the settings tab', function () {
    $team = Team::factory()->create([
        'business_hours' => businessHoursPayload()['business_hours'],
        'timezone' => 'America/Sao_Paulo',
    ]);
    $owner = User::factory()->withTeam($team)->create();

    $response = $this
        ->actingAs($owner)
        ->get(route('business-hours.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/business-hours')
        ->where('team.businessHours.monday.open', '09:00')
        ->where('team.timezone', 'America/Sao_Paulo')
        ->has('timezones')
        ->has('weekdays', 7)
        ->where('weekdays.0.value', 'monday')
        ->where('canUpdate', true)
        ->missing('members')
        ->missing('invitations'),
    );
});

test('admins can update business hours without changing company details', function () {
    $team = Team::factory()->create([
        'name' => 'Empresa original',
        'business_hours' => businessHoursPayload()['business_hours'],
        'timezone' => 'America/Sao_Paulo',
    ]);
    $owner = User::factory()->withTeam($team)->create();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $payload = businessHoursPayload();
    $payload['business_hours']['monday']['close'] = '17:30';

    $response = $this
        ->actingAs($admin)
        ->patch(route('business-hours.update'), $payload);

    $response->assertRedirect(route('business-hours.edit'));
    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Empresa original',
        'timezone' => 'America/Sao_Paulo',
    ]);
    expect($team->fresh()->business_hours['monday']['close'])->toBe('17:30');
    expect($owner->fresh()->company()->is($team))->toBeTrue();
});

test('company updates do not change business hours or timezone', function () {
    $team = Team::factory()->create([
        'business_hours' => businessHoursPayload()['business_hours'],
        'timezone' => 'America/Sao_Paulo',
    ]);
    $owner = User::factory()->withTeam($team)->create();
    $originalBusinessHours = $team->business_hours;

    $response = $this
        ->actingAs($owner)
        ->patch(route('company.update'), [
            'name' => 'Empresa atualizada',
        ]);

    $response->assertRedirect(route('company.edit'));
    expect($team->fresh()->name)->toBe('Empresa atualizada');
    $this->assertEqualsCanonicalizing(
        $originalBusinessHours,
        $team->fresh()->business_hours,
    );
    expect($team->fresh()->timezone)->toBe('America/Sao_Paulo');
});

test('business hours validation returns Portuguese messages for the invalid fields', function () {
    $user = User::factory()->withTeam()->create();
    $payload = businessHoursPayload();
    $payload['business_hours']['monday']['open'] = '9am';
    $payload['timezone'] = 'Fuso/Invalido';

    $response = $this
        ->actingAs($user)
        ->from(route('business-hours.edit'))
        ->patch(route('business-hours.update'), $payload);

    $response->assertSessionHasErrors([
        'business_hours.monday.open' => 'Informe a abertura no formato HH:MM.',
        'timezone' => 'Selecione um fuso horário válido.',
    ]);
});

test('members can read business hours as text without editable fields', function () {
    $team = Team::factory()->create([
        'business_hours' => businessHoursPayload()['business_hours'],
        'timezone' => 'America/Sao_Paulo',
    ]);
    $owner = User::factory()->withTeam($team)->create();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->get(route('business-hours.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/business-hours')
        ->where('canUpdate', false)
        ->where('team.businessHours.saturday.enabled', '0')
        ->missing('members')
        ->missing('invitations'),
    );
    expect($owner->fresh()->company()->is($team))->toBeTrue();
});

test('members cannot update business hours through a direct request', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->withTeam($team)->create();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->patch(route('business-hours.update'), businessHoursPayload());

    $response->assertForbidden();
    expect($team->fresh()->business_hours)->toBeNull();
    expect($owner->fresh()->company()->is($team))->toBeTrue();
});
