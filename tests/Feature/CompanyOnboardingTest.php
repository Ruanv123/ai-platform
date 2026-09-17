<?php

use App\Enums\TeamRole;
use App\Models\Collection;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function companyDetails(): array
{
    return [
        'name' => 'Acme Atendimento',
        'segment' => 'tecnologia',
        'website' => 'https://acme.example.com',
        'business_description' => 'Atendimento automatizado para empresas.',
        'products_services' => 'Chatbots e consultoria.',
        'target_audience' => 'Pequenas e médias empresas.',
        'business_hours' => [
            'monday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
            'sunday' => ['enabled' => '0', 'open' => null, 'close' => null],
        ],
        'timezone' => 'America/Sao_Paulo',
        'phone' => '(11) 99999-9999',
        'public_email' => 'contato@acme.example.com',
        'address' => 'São Paulo, SP',
    ];
}

test('new users are redirected to the company onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('onboarding.company'));
});

test('users without a company can render the onboarding form', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('onboarding.company'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->has('segments')
        ->has('timezones')
        ->has('weekdays', 7),
    );
});

test('users without a company can accept an invitation without onboarding', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('invitations.show', $invitation));

    $response->assertRedirect(route('dashboard'));
    expect($invitedUser->fresh()->company()->is($team))->toBeTrue();
});

test('completing onboarding creates the company and owner membership', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.company.store'), companyDetails());

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('teams', [
        'name' => 'Acme Atendimento',
        'segment' => 'tecnologia',
        'timezone' => 'America/Sao_Paulo',
    ]);

    $team = Team::query()->where('name', 'Acme Atendimento')->firstOrFail();

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'role' => TeamRole::Owner->value,
    ]);
    $this->assertDatabaseHas('collections', [
        'team_id' => $team->id,
        'name' => 'Geral',
        'is_default' => true,
    ]);
    expect($team->fresh()->business_hours['sunday']['enabled'])->toBe('0');
    expect(Collection::query()->where('team_id', $team->id)->count())->toBe(1);
    expect($user->fresh()->company()->is($team))->toBeTrue();
});

test('onboarding returns clear messages for missing required data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.company.store'), []);

    $response->assertSessionHasErrors([
        'name' => 'Informe o nome da empresa.',
        'segment' => 'Selecione o segmento da empresa.',
        'business_description' => 'Informe uma descrição do negócio.',
        'products_services' => 'Informe os produtos ou serviços.',
        'target_audience' => 'Informe o público-alvo.',
        'business_hours' => 'Informe o horário de atendimento.',
        'timezone' => 'Selecione o fuso horário.',
    ]);
    $this->assertDatabaseCount('teams', 0);
});

test('onboarding rejects a segment outside the available options', function () {
    $user = User::factory()->create();
    $details = companyDetails();
    $details['segment'] = 'segmento-inexistente';

    $response = $this->actingAs($user)->post(route('onboarding.company.store'), $details);

    $response->assertSessionHasErrors('segment');
    $this->assertDatabaseCount('teams', 0);
});

test('users with a company cannot access onboarding or create another company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();

    $getResponse = $this->actingAs($user)->get(route('onboarding.company'));
    $postResponse = $this->actingAs($user)->post(route('onboarding.company.store'), companyDetails());

    $getResponse->assertRedirect(route('dashboard'));
    $postResponse->assertRedirect(route('dashboard'));
    expect(Team::query()->count())->toBe(1);
    expect($user->fresh()->company()->is($team))->toBeTrue();
});

test('owners and admins can edit all company details', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $details = companyDetails();
    $details['name'] = 'Acme Atualizada';
    unset($details['business_hours'], $details['timezone']);

    $response = $this->actingAs($admin)->patch(route('company.update'), $details);

    $response->assertRedirect(route('company.edit'));
    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Acme Atualizada',
        'segment' => $details['segment'],
        'website' => $details['website'],
        'business_description' => $details['business_description'],
        'products_services' => $details['products_services'],
        'target_audience' => $details['target_audience'],
        'phone' => $details['phone'],
        'public_email' => $details['public_email'],
        'address' => $details['address'],
    ]);
});

test('owners can edit company details', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $details = companyDetails();
    $details['name'] = 'Acme do Owner';
    unset($details['business_hours'], $details['timezone']);

    $response = $this->actingAs($owner)->patch(route('company.update'), $details);

    $response->assertRedirect(route('company.edit'));
    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Acme do Owner',
        'segment' => $details['segment'],
        'website' => $details['website'],
        'business_description' => $details['business_description'],
        'products_services' => $details['products_services'],
        'target_audience' => $details['target_audience'],
        'phone' => $details['phone'],
        'public_email' => $details['public_email'],
        'address' => $details['address'],
    ]);
});

test('members can view but cannot edit company details', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $getResponse = $this->actingAs($member)->get(route('company.edit'));
    $postResponse = $this->actingAs($member)->patch(route('company.update'), companyDetails());

    $getResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/company')
        ->where('team.name', $team->name)
        ->where('permissions.canUpdateTeam', false),
    );
    $postResponse->assertForbidden();
});
