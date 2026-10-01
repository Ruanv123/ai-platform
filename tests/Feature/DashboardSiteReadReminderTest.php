<?php

use App\Enums\CompanyOnboardingStep;
use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an onboarding proposal opens the onboarding review and confirmation keeps the team completed', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $completedAt = now()->subMinutes(5)->startOfSecond();
    $team->update([
        'onboarding_step' => CompanyOnboardingStep::Completed,
        'onboarding_completed_at' => $completedAt,
    ]);
    $siteRead = $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => SiteReadStatus::Completed,
        'error_message' => 'Uma página do site não pôde ser lida.',
        'started_at' => $completedAt->copy()->subMinutes(2),
        'completed_at' => now(),
    ]);
    $proposal = $siteRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => [
            'business_description' => [
                'value' => 'Atendimento digital para empresas.',
                'source_url' => 'https://acme.example.com/sobre',
            ],
        ],
        'status' => SiteReadProposalStatus::Pending,
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.proposal.origin', 'onboarding')
            ->where('siteReadReminder.proposal.href', route('onboarding.company.proposal-review'))
            ->where('siteReadReminder.failure', null),
        );

    $this->withoutVite()->get(route('onboarding.company.proposal-review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding/company')
            ->where('step', CompanyOnboardingStep::ProposalReview->value)
            ->where('proposal.fields.business_description.value', 'Atendimento digital para empresas.')
            ->where('proposal.fields.business_description.sourceUrl', 'https://acme.example.com/sobre'),
        );

    $this->post(route('onboarding.company.proposal'), [
        'business_description' => 'Descrição confirmada depois de continuar.',
    ])->assertRedirect(route('dashboard'));

    expect($team->fresh()->onboarding_step)->toBe(CompanyOnboardingStep::Completed)
        ->and($team->fresh()->onboarding_completed_at->equalTo($completedAt))->toBeTrue()
        ->and($team->fresh()->business_description)->toBe('Descrição confirmada depois de continuar.')
        ->and($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Confirmed);
});

test('a proposal from a settings reread opens company settings from the dashboard', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $completedAt = now()->subMinutes(10);
    $team->update([
        'onboarding_step' => CompanyOnboardingStep::Completed,
        'onboarding_completed_at' => $completedAt,
    ]);
    $siteRead = $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => SiteReadStatus::Completed,
        'started_at' => $completedAt->copy()->addMinute(),
        'completed_at' => now(),
    ]);
    $siteRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => [
            'business_description' => [
                'value' => 'Atendimento revisto.',
                'source_url' => 'https://acme.example.com/sobre',
            ],
        ],
        'status' => SiteReadProposalStatus::Pending,
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.proposal.origin', 'settings')
            ->where('siteReadReminder.proposal.href', route('company.edit')),
        );
});

test('dashboard shows the replacement proposal and hides proposals that are no longer pending', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $completedAt = now()->subMinutes(10);
    $team->update([
        'onboarding_step' => CompanyOnboardingStep::Completed,
        'onboarding_completed_at' => $completedAt,
    ]);
    $oldRead = $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => SiteReadStatus::Completed,
        'started_at' => $completedAt->copy()->subMinute(),
        'completed_at' => now(),
    ]);
    $oldProposal = $oldRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => [],
        'status' => SiteReadProposalStatus::Replaced,
    ]);
    $newRead = $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => SiteReadStatus::Completed,
        'started_at' => $completedAt->copy()->addMinute(),
        'completed_at' => now(),
    ]);
    $newProposal = $newRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => [],
        'status' => SiteReadProposalStatus::Pending,
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.proposal.origin', 'settings')
            ->where('siteReadReminder.proposal.href', route('company.edit')),
        );

    expect($oldProposal->fresh()->status)->toBe(SiteReadProposalStatus::Replaced)
        ->and($newProposal->fresh()->status)->toBe(SiteReadProposalStatus::Pending);
});

test('dashboard does not show proposals that are no longer pending', function (SiteReadProposalStatus $status) {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $team->update([
        'onboarding_step' => CompanyOnboardingStep::Completed,
        'onboarding_completed_at' => now()->subMinutes(5),
    ]);
    $siteRead = $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => SiteReadStatus::Completed,
        'started_at' => now()->subMinutes(6),
        'completed_at' => now(),
    ]);
    $siteRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => [],
        'status' => $status,
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.proposal', null),
        );
})->with([
    'confirmed' => SiteReadProposalStatus::Confirmed,
    'discarded' => SiteReadProposalStatus::Discarded,
    'replaced' => SiteReadProposalStatus::Replaced,
]);

test('dashboard keeps polling until the onboarding proposal exists', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $completedAt = now()->subMinutes(5);
    $team->update([
        'onboarding_step' => CompanyOnboardingStep::Completed,
        'onboarding_completed_at' => $completedAt,
    ]);
    $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => SiteReadStatus::Completed,
        'started_at' => $completedAt->copy()->subMinute(),
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.polling', true)
            ->where('siteReadReminder.proposal', null),
        );
});

test('a failed read after the owner leaves points to manual company details', function (SiteReadStatus $status) {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $completedAt = now()->subMinutes(5);
    $team->update([
        'onboarding_step' => CompanyOnboardingStep::Completed,
        'onboarding_completed_at' => $completedAt,
    ]);
    $team->siteReads()->create([
        'requested_by_id' => $owner->getKey(),
        'website' => 'https://acme.example.com',
        'status' => $status,
        'error_message' => 'O site não respondeu no prazo.',
        'started_at' => $completedAt->copy()->subMinute(),
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.polling', false)
            ->where('siteReadReminder.proposal', null)
            ->where('siteReadReminder.failure.message', 'O site não respondeu no prazo.')
            ->where('siteReadReminder.failure.href', route('company.edit')),
        );

    $this->get(route('company.edit'))->assertOk();
})->with([
    'failed read' => SiteReadStatus::Failed,
    'proposal extraction error' => SiteReadStatus::Completed,
]);
