<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SiteReadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LinkSiteCollectionAgentsRequest;
use App\Http\Requests\StartSiteReadRequest;
use App\Jobs\ReadSite;
use App\Models\SiteRead;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteReadController extends Controller
{
    public function store(StartSiteReadRequest $request): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if(! $team instanceof Team, 404);

        $siteReadId = DB::transaction(function () use ($request, $team): int {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->getKey());
            $latestSiteRead = $lockedTeam->siteReads()->latest('started_at')->first();
            $availableAgainAt = $latestSiteRead?->started_at?->copy()->addHours(24);

            if ($latestSiteRead?->status === SiteReadStatus::InProgress) {
                throw ValidationException::withMessages([
                    'site_read' => $this->inProgressMessage($availableAgainAt, $team->timezone),
                ]);
            }

            if ($availableAgainAt !== null && $availableAgainAt->isFuture()) {
                throw ValidationException::withMessages([
                    'site_read' => 'Uma nova Leitura poderá ser iniciada em '.$this->formatDate($availableAgainAt, $team->timezone).'.',
                ]);
            }

            return SiteRead::withoutGlobalScopes()->create([
                'team_id' => $team->getKey(),
                'requested_by_id' => $request->user()->getKey(),
                'website' => $request->validated('website'),
                'status' => SiteReadStatus::InProgress,
                'started_at' => now(),
            ])->getKey();
        });

        ReadSite::dispatch($siteReadId);

        return to_route('company.edit');
    }

    public function linkAgents(LinkSiteCollectionAgentsRequest $request, SiteRead $siteRead): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if($team === null || $siteRead->team_id !== $team->getKey(), 404);
        abort_if($siteRead->status !== SiteReadStatus::Completed, 404);

        $siteCollection = $team->siteCollection()->firstOrFail();
        $agentIds = $request->validated('agent_ids');
        $agents = $team->agents()->whereKey($agentIds)->get();

        abort_if($agents->count() !== count($agentIds), 404);

        DB::transaction(function () use ($agents, $siteCollection): void {
            foreach ($agents as $agent) {
                Gate::authorize('update', $agent);
            }

            foreach ($agents as $agent) {
                $agent->collections()->syncWithoutDetaching([$siteCollection->getKey()]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Coleção do site ligada aos Agentes escolhidos.',
        ]);

        return to_route('company.edit');
    }

    private function inProgressMessage(?CarbonInterface $availableAgainAt, ?string $timezone): string
    {
        $message = 'Já existe uma Leitura do site em andamento. Aguarde a conclusão';

        if ($availableAgainAt !== null && $availableAgainAt->isFuture()) {
            return $message.'; será possível iniciar outra em '.$this->formatDate($availableAgainAt, $timezone).'.';
        }

        return $message.' antes de iniciar outra.';
    }

    private function formatDate(CarbonInterface $date, ?string $timezone): string
    {
        return $date->copy()->timezone($timezone ?: config('app.timezone'))->format('d/m/Y \à\s H:i');
    }
}
