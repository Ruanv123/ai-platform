<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Teams\CreateTeam;
use App\Ecommerce\ShopifyInstallationFlow;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Enums\CompanyOnboardingStep;
use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Enums\TeamSegment;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CompleteManualCompanyOnboardingRequest;
use App\Http\Requests\Onboarding\ConfirmSiteProposalOnboardingRequest;
use App\Http\Requests\Onboarding\SelectFirstAgentObjectiveRequest;
use App\Http\Requests\Onboarding\StartCompanyOnboardingRequest;
use App\Jobs\ReadSite;
use App\Models\Agent;
use App\Models\SiteRead;
use App\Models\SiteReadProposal;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $team = $user?->company();
        $siteRead = null;
        $proposal = null;
        $manualError = null;
        $step = CompanyOnboardingStep::NameAndWebsite;

        if ($team instanceof Team) {
            if ($team->isOnboardingComplete()) {
                if (! $request->routeIs('onboarding.company.proposal-review')) {
                    return to_route('dashboard');
                }

                $proposal = $team->pendingOnboardingProposal();

                if (! $proposal instanceof SiteReadProposal) {
                    return to_route('dashboard');
                }

                $step = CompanyOnboardingStep::ProposalReview;
            } else {
                $step = $team->onboarding_step ?? CompanyOnboardingStep::NameAndWebsite;
                $siteRead = $team->siteReads()->latest('started_at')->first();

                if ($step === CompanyOnboardingStep::SiteRead) {
                    if ($siteRead === null || $siteRead->status === SiteReadStatus::Failed) {
                        $manualError = $siteRead?->error_message;
                        $step = CompanyOnboardingStep::ManualCompanyDetails;
                        $team->update(['onboarding_step' => $step]);
                    } elseif ($siteRead->status === SiteReadStatus::Completed) {
                        $proposal = $siteRead->proposal()
                            ->where('status', SiteReadProposalStatus::Pending->value)
                            ->first();

                        if ($proposal instanceof SiteReadProposal) {
                            $step = CompanyOnboardingStep::ProposalReview;
                            $team->update(['onboarding_step' => $step]);
                        } elseif (filled($siteRead->error_message)) {
                            $manualError = $siteRead->error_message;
                            $step = CompanyOnboardingStep::ManualCompanyDetails;
                            $team->update(['onboarding_step' => $step]);
                        }
                    }
                }

                if ($step === CompanyOnboardingStep::ProposalReview && $proposal === null) {
                    $proposal = $team->siteReadProposals()
                        ->where('status', SiteReadProposalStatus::Pending->value)
                        ->latest('id')
                        ->first();

                    if (! $proposal instanceof SiteReadProposal) {
                        $manualError = 'Não foi possível preparar a proposta da Leitura. Preencha os dados da Empresa manualmente.';
                        $step = CompanyOnboardingStep::ManualCompanyDetails;
                        $team->update(['onboarding_step' => $step]);
                    }
                }

                if ($step === CompanyOnboardingStep::ManualCompanyDetails && $manualError === null && $siteRead?->status === SiteReadStatus::Failed) {
                    $manualError = $siteRead->error_message;
                }
            }
        }

        return Inertia::render('onboarding/company', [
            'step' => $step->value,
            'team' => $team instanceof Team ? [
                'name' => $team->name,
                'website' => $team->website,
            ] : null,
            'segments' => TeamSegment::options(),
            'objectives' => AgentObjective::onboardingOptions(),
            'timezones' => timezone_identifiers_list(),
            'weekdays' => Weekday::options(),
            'siteRead' => $this->siteReadData($siteRead),
            'proposal' => $this->proposalData($proposal),
            'manualError' => $manualError,
        ]);
    }

    public function store(StartCompanyOnboardingRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        $validated = $request->validated();
        $hasWebsite = $validated['path'] === 'site';
        $team = $createTeam->handle($user, $validated['name'], [
            'website' => $hasWebsite ? $validated['website'] : null,
            'timezone' => 'America/Sao_Paulo',
            'onboarding_step' => $hasWebsite
                ? CompanyOnboardingStep::SiteRead
                : CompanyOnboardingStep::ManualCompanyDetails,
        ]);

        if ($hasWebsite) {
            $siteRead = $team->siteReads()->create([
                'requested_by_id' => $user->getKey(),
                'website' => $validated['website'],
                'status' => SiteReadStatus::InProgress,
                'started_at' => now(),
            ]);

            ReadSite::dispatch($siteRead->getKey());
        }

        return to_route('onboarding.company');
    }

    public function continueWithoutWaiting(Request $request): RedirectResponse
    {
        $team = $request->user()?->company();
        abort_unless($team instanceof Team, 404);
        Gate::authorize('update', $team);

        if ($team->onboarding_step !== CompanyOnboardingStep::SiteRead) {
            return to_route('onboarding.company');
        }

        $latestSiteRead = $team->siteReads()->latest('started_at')->first();

        if ($latestSiteRead === null || $latestSiteRead->status === SiteReadStatus::Failed) {
            return to_route('onboarding.company');
        }

        $team->update([
            'onboarding_step' => CompanyOnboardingStep::Completed,
            'onboarding_completed_at' => now(),
        ]);

        return to_route('dashboard');
    }

    public function completeManual(CompleteManualCompanyOnboardingRequest $request): RedirectResponse
    {
        $team = $request->user()?->company();
        abort_unless($team instanceof Team, 404);

        $team->update([
            ...$request->validated(),
            'onboarding_step' => CompanyOnboardingStep::AgentObjective,
            'onboarding_completed_at' => null,
        ]);

        return to_route('onboarding.company');
    }

    public function confirmProposal(
        ConfirmSiteProposalOnboardingRequest $request,
        ShopifyInstallationFlow $shopifyInstallationFlow,
    ): RedirectResponse {
        $team = $request->user()?->company();
        abort_unless($team instanceof Team, 404);

        $onboardingWasComplete = DB::transaction(function () use ($request, $team): bool {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->getKey());
            $isOnboardingComplete = $lockedTeam->isOnboardingComplete();
            $pendingProposal = $isOnboardingComplete
                ? $lockedTeam->pendingOnboardingProposal()
                : ($lockedTeam->onboarding_step === CompanyOnboardingStep::ProposalReview
                    ? $lockedTeam->siteReadProposals()
                        ->where('status', SiteReadProposalStatus::Pending->value)
                        ->latest('id')
                        ->first()
                    : null);
            abort_unless($pendingProposal instanceof SiteReadProposal, 404);
            $proposal = SiteReadProposal::query()->lockForUpdate()->findOrFail($pendingProposal->getKey());

            $teamUpdates = $request->validated();

            if ($isOnboardingComplete) {
                $teamUpdates['onboarding_step'] = $lockedTeam->onboarding_step;
                $teamUpdates['onboarding_completed_at'] = $lockedTeam->onboarding_completed_at;
            } else {
                $teamUpdates['onboarding_step'] = CompanyOnboardingStep::AgentObjective;
                $teamUpdates['onboarding_completed_at'] = null;
            }

            $lockedTeam->update($teamUpdates);
            $proposal->update(['status' => SiteReadProposalStatus::Confirmed]);

            return $isOnboardingComplete;
        });

        return $onboardingWasComplete
            ? $this->afterOnboarding($request, $shopifyInstallationFlow)
            : to_route('onboarding.company');
    }

    public function storeFirstAgent(
        SelectFirstAgentObjectiveRequest $request,
        ShopifyInstallationFlow $shopifyInstallationFlow,
    ): RedirectResponse {
        $team = $request->user()?->company();
        abort_unless($team instanceof Team, 404);

        $objective = AgentObjective::from($request->validated('objective'));
        $agent = DB::transaction(function () use ($team, $objective): Agent {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->getKey());
            abort_unless($lockedTeam->onboarding_step === CompanyOnboardingStep::AgentObjective, 403);

            $agent = $lockedTeam->agents()->create([
                ...$objective->startingPoints(),
                'objective' => $objective,
                'tone' => AgentTone::Friendly,
                'uses_emojis' => false,
                'response_length' => AgentResponseLength::Short,
                'is_active' => false,
                'shop_tools_enabled' => false,
            ]);

            $lockedTeam->update([
                'onboarding_step' => CompanyOnboardingStep::Completed,
                'onboarding_completed_at' => now(),
            ]);

            return $agent;
        });

        return $shopifyInstallationFlow->redirectAfterAuthentication($request)
            ?? to_route('agents.edit', ['agent' => $agent, 'panel' => 'playground']);
    }

    private function siteReadData(?SiteRead $siteRead): ?array
    {
        if ($siteRead === null) {
            return null;
        }

        return [
            'status' => $siteRead->status->value,
            'statusLabel' => $siteRead->status->label(),
            'foundPagesCount' => $siteRead->found_pages_count,
            'readPagesCount' => $siteRead->pages()->where('status', SiteReadPageStatus::Read->value)->count(),
            'failedPagesCount' => $siteRead->pages()->where('status', SiteReadPageStatus::Failed->value)->count(),
            'errorMessage' => $siteRead->error_message,
        ];
    }

    private function proposalData(?SiteReadProposal $proposal): ?array
    {
        if ($proposal === null) {
            return null;
        }

        $fields = [];

        foreach ($proposal->proposed_fields ?? [] as $name => $field) {
            if (! is_array($field) || ! is_string($field['source_url'] ?? null)) {
                continue;
            }

            $fields[$name] = [
                'value' => $field['value'] ?? null,
                'sourceUrl' => $field['source_url'],
            ];
        }

        return ['fields' => $fields];
    }

    private function afterOnboarding(Request $request, ShopifyInstallationFlow $shopifyInstallationFlow): RedirectResponse
    {
        return $shopifyInstallationFlow->redirectAfterAuthentication($request) ?? to_route('dashboard');
    }
}
