<?php

use App\Enums\CompanyOnboardingStep;
use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Enums\TeamRole;
use App\Jobs\ReadSite;
use App\Models\Collection;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function manualCompanyOnboardingDetails(): array
{
    return [
        'segment' => 'tecnologia',
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

function siteOnboardingProposalFields(string $sourceUrl): array
{
    return [
        'segment' => ['value' => 'tecnologia', 'source_url' => $sourceUrl],
        'business_description' => ['value' => 'Soluções para atendimento.', 'source_url' => $sourceUrl],
        'products_services' => ['value' => 'Chatbots.', 'source_url' => $sourceUrl],
        'target_audience' => ['value' => 'Pequenas empresas.', 'source_url' => $sourceUrl],
        'phone' => ['value' => '(11) 1234-5678', 'source_url' => $sourceUrl],
        'public_email' => ['value' => 'oi@acme.example.com', 'source_url' => $sourceUrl],
        'address' => ['value' => 'São Paulo, SP', 'source_url' => $sourceUrl],
        'business_hours' => [
            'value' => [
                'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            ],
            'source_url' => $sourceUrl,
        ],
        'brand_voice' => ['value' => 'Direta e acolhedora.', 'source_url' => $sourceUrl],
        'brand_voice_examples' => ['value' => ['Conte com a gente.', 'Vamos ajudar.'], 'source_url' => $sourceUrl],
    ];
}

test('new users are redirected to the company onboarding', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('onboarding.company'));
});

test('users without a company can render the first onboarding step', function () {
    $user = User::factory()->create();

    $response = $this->withoutVite()->actingAs($user)->get(route('onboarding.company'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->where('step', CompanyOnboardingStep::NameAndWebsite->value)
        ->has('segments')
        ->has('timezones')
        ->has('weekdays', 7),
    );
});

test('site onboarding creates the company with the default timezone and starts a read', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ]);

    $response->assertRedirect(route('onboarding.company'));
    $team = $user->fresh()->company();
    $siteRead = $team->siteReads()->firstOrFail();

    expect($team->onboarding_step)->toBe(CompanyOnboardingStep::SiteRead)
        ->and($team->onboarding_completed_at)->toBeNull()
        ->and($team->website)->toBe('https://acme.example.com')
        ->and($team->timezone)->toBe('America/Sao_Paulo')
        ->and($siteRead->status)->toBe(SiteReadStatus::InProgress)
        ->and($siteRead->started_at)->not->toBeNull();

    Queue::assertPushed(ReadSite::class, fn (ReadSite $job): bool => $job->siteReadId === $siteRead->getKey());
});

test('site onboarding rejects Instagram and WhatsApp before creating a company', function (string $website) {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => $website,
    ]);

    $response->assertSessionHasErrors([
        'website' => 'Instagram e endereços de WhatsApp não podem ser lidos. Informe o site da Empresa ou preencha os dados manualmente.',
    ]);
    $this->assertDatabaseCount('teams', 0);
    Queue::assertNotPushed(ReadSite::class);
})->with([
    'Instagram' => 'https://instagram.com/acme',
    'WhatsApp short link' => 'https://wa.me/5511999999999',
    'WhatsApp API subdomain' => 'https://api.whatsapp.com/send?phone=5511999999999',
]);

test('manual onboarding creates a company first and completes it from the manual form', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $startResponse = $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'manual',
        'name' => 'Acme Atendimento',
    ]);

    $startResponse->assertRedirect(route('onboarding.company'));
    $team = $user->fresh()->company();

    expect($team->onboarding_step)->toBe(CompanyOnboardingStep::ManualCompanyDetails)
        ->and($team->website)->toBeNull();

    $formResponse = $this->withoutVite()->get(route('onboarding.company'));

    $formResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->where('step', CompanyOnboardingStep::ManualCompanyDetails->value)
        ->where('team.name', 'Acme Atendimento'),
    );

    $completionResponse = $this->post(route('onboarding.company.manual'), manualCompanyOnboardingDetails());

    $completionResponse->assertRedirect(route('dashboard'));
    $team->refresh();

    expect($team->onboarding_step)->toBe(CompanyOnboardingStep::Completed)
        ->and($team->onboarding_completed_at)->not->toBeNull()
        ->and($team->segment)->toBe('tecnologia')
        ->and($team->business_description)->toBe('Atendimento automatizado para empresas.')
        ->and($team->timezone)->toBe('America/Sao_Paulo')
        ->and(count($team->business_hours))->toBe(7)
        ->and($team->business_hours['monday']['enabled'])->toBe(true)
        ->and($team->business_hours['monday']['open'])->toBe('09:00')
        ->and($team->business_hours['sunday']['enabled'])->toBe(false)
        ->and($team->business_hours['sunday']['open'])->toBeNull();

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->getKey(),
        'user_id' => $user->getKey(),
        'role' => TeamRole::Owner->value,
    ]);
    $this->assertDatabaseHas('collections', [
        'team_id' => $team->getKey(),
        'name' => 'Geral',
        'is_default' => true,
    ]);
    Queue::assertNotPushed(ReadSite::class);
    expect(Collection::query()->where('team_id', $team->getKey())->count())->toBe(1);
});

test('manual onboarding requires valid company details before completion', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'manual',
        'name' => 'Acme Atendimento',
    ])->assertRedirect(route('onboarding.company'));

    $team = $user->fresh()->company();
    $invalidDetails = manualCompanyOnboardingDetails();
    unset($invalidDetails['products_services'], $invalidDetails['target_audience'], $invalidDetails['business_hours']);
    $invalidDetails['segment'] = 'invalid-segment';
    $invalidDetails['business_description'] = '';
    $invalidDetails['timezone'] = 'Invalid/Timezone';

    $response = $this->post(route('onboarding.company.manual'), $invalidDetails);

    $response->assertSessionHasErrors([
        'segment',
        'business_description',
        'products_services',
        'target_audience',
        'business_hours',
        'timezone',
    ]);
    expect($team->fresh()->onboarding_step)->toBe(CompanyOnboardingStep::ManualCompanyDetails)
        ->and($team->fresh()->onboarding_completed_at)->toBeNull()
        ->and($team->fresh()->business_description)->toBeNull();
});

test('the onboarding progress page reports found, read, and failed pages', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ]);
    $team = $user->fresh()->company();
    $siteRead = $team->siteReads()->firstOrFail();
    $siteRead->update(['found_pages_count' => 3]);
    $siteRead->pages()->create([
        'team_id' => $team->getKey(),
        'url' => 'https://acme.example.com/',
        'title' => 'Início',
        'status' => SiteReadPageStatus::Read,
    ]);
    $siteRead->pages()->create([
        'team_id' => $team->getKey(),
        'url' => 'https://acme.example.com/sobre',
        'title' => 'Sobre',
        'status' => SiteReadPageStatus::Failed,
    ]);

    $response = $this->withoutVite()->get(route('onboarding.company'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->where('step', CompanyOnboardingStep::SiteRead->value)
        ->where('siteRead.foundPagesCount', 3)
        ->where('siteRead.readPagesCount', 1)
        ->where('siteRead.failedPagesCount', 1),
    );
});

test('a failed site read resumes onboarding in the manual form with its reason', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ]);
    $team = $user->fresh()->company();
    $team->siteReads()->firstOrFail()->update([
        'status' => SiteReadStatus::Failed,
        'error_message' => 'O site não respondeu.',
        'completed_at' => now(),
    ]);

    $response = $this->withoutVite()->get(route('onboarding.company'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->where('step', CompanyOnboardingStep::ManualCompanyDetails->value)
        ->where('manualError', 'O site não respondeu.'),
    );
    expect($team->fresh()->onboarding_step)->toBe(CompanyOnboardingStep::ManualCompanyDetails);
});

test('a completed read opens the proposal with source links and saves edited values as confirmed', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ]);
    $team = $user->fresh()->company();
    $siteRead = $team->siteReads()->firstOrFail();
    $siteRead->update([
        'status' => SiteReadStatus::Completed,
        'completed_at' => now(),
        'found_pages_count' => 1,
    ]);
    $sourceUrl = 'https://acme.example.com/sobre';
    $proposal = $siteRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => siteOnboardingProposalFields($sourceUrl),
        'status' => SiteReadProposalStatus::Pending,
    ]);

    $reviewResponse = $this->withoutVite()->get(route('onboarding.company'));

    $reviewResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->where('step', CompanyOnboardingStep::ProposalReview->value)
        ->where('proposal.fields.business_description.value', 'Soluções para atendimento.')
        ->where('proposal.fields.business_description.sourceUrl', $sourceUrl),
    );

    $completionResponse = $this->post(route('onboarding.company.proposal'), [
        'segment' => 'servicos',
        'business_description' => 'Descrição revisada pela equipe.',
        'products_services' => 'Consultoria revisada.',
        'target_audience' => 'Empresas com atendimento digital.',
        'phone' => '(11) 99999-9999',
        'public_email' => 'contato@acme.example.com',
        'address' => 'São Paulo, SP',
        'business_hours' => [
            'monday' => ['enabled' => '1', 'open' => '10:00', 'close' => '17:00'],
            'tuesday' => ['enabled' => '0', 'open' => '', 'close' => ''],
        ],
        'brand_voice' => 'Direta e muito acolhedora.',
        'brand_voice_examples' => ['Conte com a nossa equipe.', 'Estamos aqui para ajudar.'],
    ]);

    $completionResponse->assertRedirect(route('dashboard'));
    $team->refresh();

    expect($team->onboarding_step)->toBe(CompanyOnboardingStep::Completed)
        ->and($team->onboarding_completed_at)->not->toBeNull()
        ->and($team->segment)->toBe('servicos')
        ->and($team->business_description)->toBe('Descrição revisada pela equipe.')
        ->and($team->products_services)->toBe('Consultoria revisada.')
        ->and($team->timezone)->toBe('America/Sao_Paulo')
        ->and($team->business_hours['monday']['enabled'])->toBe(true)
        ->and($team->business_hours['monday']['open'])->toBe('10:00')
        ->and($team->business_hours['monday']['close'])->toBe('17:00')
        ->and($team->business_hours['tuesday']['enabled'])->toBe(false)
        ->and($team->business_hours['tuesday']['open'])->toBeNull()
        ->and($team->business_hours['tuesday']['close'])->toBeNull()
        ->and($team->business_hours['sunday']['enabled'])->toBe(false)
        ->and($team->brand_voice)->toBe('Direta e muito acolhedora.')
        ->and($team->brand_voice_examples)->toBe(['Conte com a nossa equipe.', 'Estamos aqui para ajudar.'])
        ->and($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Confirmed);
});

test('proposal review requires the brand voice description and 2 to 4 examples together', function () {
    Queue::fake([ReadSite::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.company.store'), [
        'path' => 'site',
        'name' => 'Acme Atendimento',
        'website' => 'https://acme.example.com',
    ]);
    $team = $user->fresh()->company();
    $siteRead = $team->siteReads()->firstOrFail();
    $siteRead->update(['status' => SiteReadStatus::Completed, 'completed_at' => now()]);
    $siteRead->proposal()->create([
        'team_id' => $team->getKey(),
        'proposed_fields' => siteOnboardingProposalFields('https://acme.example.com/sobre'),
        'status' => SiteReadProposalStatus::Pending,
    ]);
    $this->get(route('onboarding.company'));

    $response = $this->post(route('onboarding.company.proposal'), [
        'brand_voice' => 'Direta e acolhedora.',
        'brand_voice_examples' => ['Conte com a gente.'],
    ]);

    $response->assertSessionHasErrors('brand_voice_examples');
    expect($team->fresh()->onboarding_step)->toBe(CompanyOnboardingStep::ProposalReview)
        ->and($team->fresh()->onboarding_completed_at)->toBeNull()
        ->and($team->fresh()->brand_voice)->toBeNull();
});

test('pending teams resume onboarding while legacy teams continue to the dashboard', function () {
    $pendingOwner = User::factory()->withTeam()->create();
    $pendingTeam = $pendingOwner->company();
    $pendingTeam->update([
        'onboarding_step' => CompanyOnboardingStep::ManualCompanyDetails,
        'onboarding_completed_at' => null,
    ]);
    $legacyOwner = User::factory()->withTeam()->create();

    $pendingResponse = $this->withoutVite()->actingAs($pendingOwner)->get(route('onboarding.company'));
    $pendingDashboard = $this->actingAs($pendingOwner)->get(route('dashboard'));
    $legacyOnboarding = $this->actingAs($legacyOwner)->get(route('onboarding.company'));

    $pendingResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/company')
        ->where('step', CompanyOnboardingStep::ManualCompanyDetails->value),
    );
    $pendingDashboard->assertRedirect(route('onboarding.company'));
    $legacyOnboarding->assertRedirect(route('dashboard'));
    expect($legacyOwner->fresh()->company()->isOnboardingComplete())->toBeTrue();
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

test('users with a completed company cannot access onboarding or create another company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();

    $getResponse = $this->actingAs($user)->get(route('onboarding.company'));
    $postResponse = $this->post(route('onboarding.company.store'), [
        'path' => 'manual',
        'name' => 'Outra Empresa',
    ]);

    $getResponse->assertRedirect(route('dashboard'));
    $postResponse->assertRedirect(route('dashboard'));
    expect(Team::query()->count())->toBe(1)
        ->and($user->fresh()->company()->is($team))->toBeTrue();
});
