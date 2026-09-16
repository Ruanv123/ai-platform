<?php

use App\Enums\ChannelStatus;
use App\Enums\DocumentStatus;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardCompanyDetails(): array
{
    return [
        'segment' => 'tecnologia',
        'business_description' => 'Atendimento automatizado para empresas.',
        'products_services' => 'Chatbots e consultoria.',
        'target_audience' => 'Pequenas e médias empresas.',
        'business_hours' => [
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
        ],
        'timezone' => 'America/Sao_Paulo',
    ];
}

test('guests are redirected to the login page', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard shows an empty activation checklist and summary for a new company', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('checklist', 5)
        ->where('checklist.0.key', 'company')
        ->where('checklist.0.completed', false)
        ->where('checklist.0.href', route('company.edit'))
        ->where('checklist.1.completed', false)
        ->where('checklist.1.href', route('agents.index'))
        ->where('checklist.2.completed', false)
        ->where('checklist.2.href', route('collections.index'))
        ->where('checklist.3.completed', false)
        ->where('checklist.3.href', route('channels.index'))
        ->where('checklist.4.completed', false)
        ->where('checklist.4.href', route('channels.index'))
        ->where('summary.agents.active', 0)
        ->where('summary.agents.total', 0)
        ->where('summary.documents.ready', 0)
        ->where('summary.documents.failed', 0)
        ->where('summary.storage.usedBytes', 0)
        ->where('summary.storage.limitBytes', 500 * 1024 * 1024)
        ->where('summary.storage.usedPercentage', 0)
        ->where('summary.whatsapp.status', null)
        ->where('summary.whatsapp.statusLabel', 'Não configurado'),
    );
});

test('dashboard reflects a partially configured company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['name' => 'Acme Atendimento', ...dashboardCompanyDetails()]);
    $collection = Collection::factory()->for($team)->create();
    Agent::factory()->for($team)->create();
    Document::factory()->for($collection)->create([
        'status' => DocumentStatus::Ready,
        'size_bytes' => 120,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('checklist.0.completed', true)
        ->where('checklist.1.completed', true)
        ->where('checklist.2.completed', true)
        ->where('checklist.3.completed', false)
        ->where('checklist.4.completed', false)
        ->where('summary.agents.active', 1)
        ->where('summary.agents.total', 1)
        ->where('summary.documents.ready', 1)
        ->where('summary.documents.failed', 0)
        ->where('summary.storage.usedBytes', 120)
        ->where('summary.whatsapp.status', null),
    );
});

test('dashboard reflects a fully activated company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['name' => 'Acme Atendimento', ...dashboardCompanyDetails()]);
    $collection = Collection::factory()->for($team)->create();
    $activeAgent = Agent::factory()->for($team)->create();
    Agent::factory()->for($team)->inactive()->create();
    Document::factory()->for($collection)->create([
        'status' => DocumentStatus::Ready,
        'size_bytes' => 120,
    ]);
    Document::factory()->for($collection)->create([
        'status' => DocumentStatus::Failed,
        'size_bytes' => 80,
    ]);
    Channel::factory()->for($team)->create([
        'agent_id' => $activeAgent->id,
        'status' => ChannelStatus::Connected,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('checklist.0.completed', true)
        ->where('checklist.1.completed', true)
        ->where('checklist.2.completed', true)
        ->where('checklist.3.completed', true)
        ->where('checklist.4.completed', true)
        ->where('summary.agents.active', 1)
        ->where('summary.agents.total', 2)
        ->where('summary.documents.ready', 1)
        ->where('summary.documents.failed', 1)
        ->where('summary.storage.usedBytes', 200)
        ->where('summary.whatsapp.status', 'connected')
        ->where('summary.whatsapp.statusLabel', 'Conectado'),
    );
});

test('dashboard only includes resources from the users company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $otherTeam = Team::factory()->create(['name' => 'Outra Empresa']);
    $otherCollection = Collection::factory()->for($otherTeam)->create();
    Agent::factory()->for($otherTeam)->create();
    Document::factory()->for($otherCollection)->create([
        'status' => DocumentStatus::Ready,
        'size_bytes' => 900,
    ]);
    Channel::factory()->for($otherTeam)->create([
        'status' => ChannelStatus::Connected,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('summary.agents.total', 0)
        ->where('summary.documents.ready', 0)
        ->where('summary.storage.usedBytes', 0)
        ->where('summary.whatsapp.status', null)
        ->where('checklist.1.completed', false)
        ->where('checklist.2.completed', false)
        ->where('checklist.3.completed', false),
    );
});

test('dashboard does not expose pending invitations', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->withTeam()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->missing('pendingInvitations'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->withTeam()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->missing('pendingInvitations'),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->withTeam()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->missing('pendingInvitations'),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->withTeam()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->missing('pendingInvitations'),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});
