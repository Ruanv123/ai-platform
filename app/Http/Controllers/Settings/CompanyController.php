<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Enums\TeamSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateCompanyRequest;
use App\Knowledge\SiteReadProposalService;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Show the current team's company settings.
     */
    public function edit(Request $request, SiteReadProposalService $proposalService): Response
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);

        $canUpdate = $request->user()->can('update', $team);
        $latestSiteRead = $team->siteReads()->with('pages')->latest('started_at')->first();
        $availableAgainAt = $latestSiteRead?->started_at?->copy()->addHours(24);
        $siteReadInProgress = $latestSiteRead?->status === SiteReadStatus::InProgress;
        $linkableAgents = collect();
        $pendingProposal = $team->siteReadProposals()
            ->where('status', SiteReadProposalStatus::Pending)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (
            $request->user()->can('create', Agent::class)
            && $latestSiteRead?->status === SiteReadStatus::Completed
            && ($siteCollection = $team->siteCollection()->first()) !== null
        ) {
            $linkableAgents = $team->agents()
                ->whereDoesntHave('collections', fn (Builder $query) => $query->where('collections.id', $siteCollection->getKey()))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ])
                ->values();
        }

        return Inertia::render('settings/company', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'segment' => $team->segment,
                'website' => $team->website,
                'businessDescription' => $team->business_description,
                'productsServices' => $team->products_services,
                'targetAudience' => $team->target_audience,
                'phone' => $team->phone,
                'publicEmail' => $team->public_email,
                'address' => $team->address,
                'brandVoice' => $team->brand_voice,
                'brandVoiceExamples' => $team->brand_voice_examples,
                'agentPauseDurationMinutes' => $team->agent_pause_duration_minutes,
            ],
            'segments' => TeamSegment::options(),
            'canUpdate' => $canUpdate,
            'siteRead' => [
                'website' => $team->website,
                'hasNewWebsite' => filled($team->website)
                    && $latestSiteRead !== null
                    && $team->website !== $latestSiteRead->website,
                'canStart' => $canUpdate
                    && filled($team->website)
                    && ! $siteReadInProgress
                    && ($availableAgainAt === null || ! $availableAgainAt->isFuture()),
                'availableAgainAt' => $availableAgainAt?->timezone($team->timezone ?: config('app.timezone'))->format('d/m/Y \à\s H:i'),
                'latest' => $latestSiteRead === null ? null : [
                    'id' => $latestSiteRead->getKey(),
                    'website' => $latestSiteRead->website,
                    'resolvedWebsite' => $latestSiteRead->resolved_website,
                    'status' => $latestSiteRead->status->value,
                    'statusLabel' => $latestSiteRead->status->label(),
                    'startedAt' => $latestSiteRead->started_at->timezone($team->timezone ?: config('app.timezone'))->format('d/m/Y H:i'),
                    'completedAt' => $latestSiteRead->completed_at?->timezone($team->timezone ?: config('app.timezone'))->format('d/m/Y H:i'),
                    'foundPagesCount' => $latestSiteRead->found_pages_count,
                    'readPagesCount' => $latestSiteRead->pages->where('status', SiteReadPageStatus::Read)->count(),
                    'failedPagesCount' => $latestSiteRead->pages->where('status', SiteReadPageStatus::Failed)->count(),
                    'errorMessage' => $latestSiteRead->error_message,
                    'linkableAgents' => $linkableAgents,
                    'pages' => $latestSiteRead->pages->sortBy('id')->map(static fn ($page): array => [
                        'id' => $page->getKey(),
                        'url' => $page->url,
                        'title' => $page->title,
                        'status' => $page->status->value,
                        'errorMessage' => $page->error_message,
                    ])->values(),
                ],
            ],
            'proposal' => $pendingProposal === null ? null : [
                'id' => $pendingProposal->getKey(),
                'fields' => $proposalService->differences($pendingProposal, $team),
            ],
        ]);
    }

    /**
     * Update the current team's company settings.
     */
    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team, 404);

        $validated = $request->safe()->except([
            'agent_pause_duration_hours',
            'agent_pause_duration_minutes_part',
        ]);
        $team->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dados da Empresa atualizados.']);

        return to_route('company.edit');
    }
}
