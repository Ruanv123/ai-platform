<?php

use App\Enums\CompanyOnboardingStep;
use App\Enums\SiteReadStatus;
use App\Enums\TeamRole;
use App\Jobs\ReadSite;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner can finish onboarding while the site read continues in the background', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ])->assertRedirect(route('onboarding.company'));

    $team = $user->fresh()->company();
    $siteRead = $team->siteReads()->firstOrFail();
    $siteRead->update(['started_at' => now()->subMinute()]);

    $this->withoutVite()->get(route('onboarding.company'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('step', CompanyOnboardingStep::SiteRead->value),
        );

    $this->post(route('onboarding.company.continue'))
        ->assertRedirect(route('dashboard'));

    $team->refresh();

    expect($team->onboarding_step)->toBe(CompanyOnboardingStep::Completed)
        ->and($team->onboarding_completed_at)->not->toBeNull()
        ->and($siteRead->fresh()->status)->toBe(SiteReadStatus::InProgress);

    Queue::assertPushed(ReadSite::class, fn (ReadSite $job): bool => $job->siteReadId === $siteRead->getKey());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteReadReminder.polling', true)
            ->where('siteReadReminder.proposal', null)
            ->where('siteReadReminder.failure', null),
        );
});

test('the onboarding progress can only be completed by a team owner', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ]);

    $team = $owner->fresh()->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member)
        ->post(route('onboarding.company.continue'))
        ->assertForbidden();

    expect($team->fresh()->onboarding_step)->toBe(CompanyOnboardingStep::SiteRead)
        ->and($team->fresh()->onboarding_completed_at)->toBeNull();
});
