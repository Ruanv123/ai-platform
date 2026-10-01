<?php

use App\Enums\SiteReadStatus;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\SiteRead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('offers only same-company Agents that are not linked to the completed site read collection', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $linkedAgent = Agent::factory()->for($team)->create(['name' => 'Lia']);
    $unlinkedAgent = Agent::factory()->for($team)->create(['name' => 'Bia']);
    $otherTeamAgent = Agent::factory()->create(['name' => 'Outro']);
    $siteCollection = Collection::factory()->for($team)->create([
        'name' => 'Site',
        'is_site_collection' => true,
    ]);
    $linkedAgent->collections()->attach($siteCollection);
    SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'requested_by_id' => $user->getKey(),
        'website' => 'https://site.example.test/',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('company.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/company')
        ->has('siteRead.latest.linkableAgents', 1)
        ->where('siteRead.latest.linkableAgents.0.id', $unlinkedAgent->id)
        ->where('siteRead.latest.linkableAgents.0.name', 'Bia')
        ->missing('siteRead.latest.linkableAgents.1.id'),
    );
    expect($otherTeamAgent->team_id)->not->toBe($team->getKey());
});

it('links only explicitly selected Agents without detaching their other Collections', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $selectedAgent = Agent::factory()->for($team)->create();
    $unselectedAgent = Agent::factory()->for($team)->create();
    $manualCollection = Collection::factory()->for($team)->create(['name' => 'Manual']);
    $selectedAgent->collections()->attach($manualCollection);
    $siteCollection = Collection::factory()->for($team)->create([
        'name' => 'Site',
        'is_site_collection' => true,
    ]);
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'requested_by_id' => $user->getKey(),
        'website' => 'https://site.example.test/',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read.agents', $siteRead), [
        'agent_ids' => [$selectedAgent->id],
    ]);

    $response->assertRedirect(route('company.edit'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Coleção do site ligada aos Agentes escolhidos.',
    ]);
    expect($selectedAgent->fresh()->collections()->pluck('collections.id')->all())
        ->toContain($siteCollection->id, $manualCollection->id)
        ->and($unselectedAgent->fresh()->collections()->whereKey($siteCollection->id)->exists())->toBeFalse();
});

it('rejects Agents from another company without attaching the site Collection', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $otherTeamAgent = Agent::factory()->create();
    $siteCollection = Collection::factory()->for($team)->create([
        'name' => 'Site',
        'is_site_collection' => true,
    ]);
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'requested_by_id' => $user->getKey(),
        'website' => 'https://site.example.test/',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read.agents', $siteRead), [
        'agent_ids' => [$otherTeamAgent->id],
    ]);

    $response->assertSessionHasErrors([
        'agent_ids.0' => 'Selecione apenas Agentes da sua Empresa.',
    ]);
    expect($otherTeamAgent->fresh()->collections()->whereKey($siteCollection->id)->exists())->toBeFalse();
});

it('returns 404 when a company submits another company site read', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->withTeam()->create();
    $otherTeam = $otherUser->company();
    $otherSiteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $otherTeam->getKey(),
        'requested_by_id' => $otherUser->getKey(),
        'website' => 'https://other.example.test/',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read.agents', $otherSiteRead), [
        'agent_ids' => [Agent::factory()->for($user->company())->create()->id],
    ]);

    $response->assertNotFound();
});

it('forbids Members from linking the site Collection to Agents', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $agent = Agent::factory()->for($team)->create();
    $siteCollection = Collection::factory()->for($team)->create([
        'name' => 'Site',
        'is_site_collection' => true,
    ]);
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://site.example.test/',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($member)->post(route('company.site-read.agents', $siteRead), [
        'agent_ids' => [$agent->id],
    ]);

    $response->assertForbidden();
    expect($agent->fresh()->collections()->whereKey($siteCollection->id)->exists())->toBeFalse();
});

it('redirects guests to login when they try to link a site Collection', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $agent = Agent::factory()->for($team)->create();
    $siteCollection = Collection::factory()->for($team)->create([
        'name' => 'Site',
        'is_site_collection' => true,
    ]);
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'requested_by_id' => $user->getKey(),
        'website' => 'https://site.example.test/',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->post(route('company.site-read.agents', $siteRead), [
        'agent_ids' => [$agent->id],
    ]);

    $response->assertRedirect(route('login'));
    expect($agent->fresh()->collections()->whereKey($siteCollection->id)->exists())->toBeFalse();
});
