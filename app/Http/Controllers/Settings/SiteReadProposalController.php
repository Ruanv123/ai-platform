<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SiteReadProposalStatus;
use App\Http\Controllers\Controller;
use App\Knowledge\SiteReadProposalService;
use App\Models\SiteReadProposal;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SiteReadProposalController extends Controller
{
    public function acceptField(
        Request $request,
        SiteReadProposal $proposal,
        string $field,
        SiteReadProposalService $proposalService,
    ): RedirectResponse {
        $team = $this->authorizeProposal($request, $proposal);
        abort_unless($proposal->status === SiteReadProposalStatus::Pending, 404);
        abort_unless(array_key_exists($field, $proposalService->differences($proposal, $team)), 404);

        $proposalService->acceptFields($proposal, $team, [$field]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Campo aceito na Empresa.',
        ]);

        return to_route('company.edit');
    }

    public function acceptAll(Request $request, SiteReadProposal $proposal, SiteReadProposalService $proposalService): RedirectResponse
    {
        $team = $this->authorizeProposal($request, $proposal);
        abort_unless($proposal->status === SiteReadProposalStatus::Pending, 404);

        $proposalService->acceptFields($proposal, $team, array_keys($proposalService->differences($proposal, $team)));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dados propostos aceitos na Empresa.',
        ]);

        return to_route('company.edit');
    }

    public function discard(Request $request, SiteReadProposal $proposal, SiteReadProposalService $proposalService): RedirectResponse
    {
        $this->authorizeProposal($request, $proposal);
        abort_unless($proposal->status === SiteReadProposalStatus::Pending, 404);

        $proposalService->discard($proposal);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proposta descartada.',
        ]);

        return to_route('company.edit');
    }

    private function authorizeProposal(Request $request, SiteReadProposal $proposal): Team
    {
        $team = $request->user()->company();

        abort_if($team === null || $proposal->team_id !== $team->getKey(), 404);
        Gate::authorize('update', $team);

        return $team;
    }
}
