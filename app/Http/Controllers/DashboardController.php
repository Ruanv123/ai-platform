<?php

namespace App\Http\Controllers;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\DocumentStatus;
use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Knowledge\DocumentStorageQuota;
use App\Models\FlowEventDelivery;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DocumentStorageQuota $quota) {}

    public function __invoke(Request $request): Response
    {
        $team = $request->user()?->company();

        abort_if($team === null, 404);

        $agentCount = $team->agents()->count();
        $activeAgentCount = $team->agents()->where('is_active', true)->count();
        $readyDocumentCount = $team->documents()
            ->where('status', DocumentStatus::Ready->value)
            ->count();
        $failedDocumentCount = $team->documents()
            ->where('status', DocumentStatus::Failed->value)
            ->count();
        $whatsapp = $team->channels()
            ->where('type', ChannelType::WhatsApp->value)
            ->first(['id', 'status']);
        $hasConnectedWhatsApp = $team->channels()
            ->where('type', ChannelType::WhatsApp->value)
            ->where('status', ChannelStatus::Connected->value)
            ->exists();
        $flowHealth = [
            'failed' => $team->flowExecutions()->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
            'stuck' => $team->flowExecutions()->stuck()->count(),
            'href' => route('flows.index', ['tab' => 'executions']),
        ];
        $webhookFailures = [
            'count' => FlowEventDelivery::withoutGlobalScopes()->where('team_id', $team->getKey())->where('status', 'failed')->count(),
            'href' => route('integrations.webhook-failures.index'),
        ];
        $hasLinkedAgent = $team->channels()
            ->where('type', ChannelType::WhatsApp->value)
            ->whereNotNull('agent_id')
            ->whereHas('agent')
            ->exists();
        $siteReadReminder = $this->siteReadReminder($team);

        return Inertia::render('dashboard', [
            'checklist' => [
                [
                    'key' => 'company',
                    'label' => 'Dados da Empresa',
                    'description' => 'Complete as informações do seu negócio.',
                    'completed' => $this->companyIsComplete($team),
                    'href' => route('company.edit'),
                ],
                [
                    'key' => 'agent',
                    'label' => 'Primeiro Agente criado',
                    'description' => 'Crie um Agente para começar a atender.',
                    'completed' => $agentCount > 0,
                    'href' => route('agents.index'),
                ],
                [
                    'key' => 'document',
                    'label' => 'Primeiro documento pronto',
                    'description' => 'Adicione conhecimento para o seu Agente.',
                    'completed' => $readyDocumentCount > 0,
                    'href' => route('collections.index'),
                ],
                [
                    'key' => 'whatsapp',
                    'label' => 'WhatsApp conectado',
                    'description' => 'Configure o canal que receberá seus atendimentos.',
                    'completed' => $hasConnectedWhatsApp,
                    'href' => route('channels.whatsapp.edit'),
                ],
                [
                    'key' => 'agent_linked',
                    'label' => 'Agente vinculado ao WhatsApp',
                    'description' => 'Escolha qual Agente responderá no WhatsApp.',
                    'completed' => $hasLinkedAgent,
                    'href' => route('channels.whatsapp.edit'),
                ],
            ],
            'summary' => [
                'agents' => [
                    'active' => $activeAgentCount,
                    'total' => $agentCount,
                ],
                'documents' => [
                    'ready' => $readyDocumentCount,
                    'failed' => $failedDocumentCount,
                ],
                'storage' => $this->storageSummary($team),
                'whatsapp' => [
                    'status' => $whatsapp?->status?->value,
                    'statusLabel' => $whatsapp?->status?->label() ?? 'Não configurado',
                ],
                'flowHealth' => $flowHealth,
                'webhookFailures' => $webhookFailures,
            ],
            'siteReadReminder' => $siteReadReminder,
        ]);
    }

    private function siteReadReminder(Team $team): array
    {
        $pendingProposal = $team->siteReadProposals()
            ->where('status', SiteReadProposalStatus::Pending->value)
            ->latest('id')
            ->first();
        $onboardingProposal = $team->pendingOnboardingProposal();
        $onboardingSiteRead = $team->onboarding_completed_at === null
            ? null
            : $team->siteReads()
                ->where('started_at', '<=', $team->onboarding_completed_at)
                ->latest('started_at')
                ->first();
        $proposalIsMissing = $onboardingSiteRead !== null && ! $onboardingSiteRead->proposal()->exists();
        $proposalExtractionFailed = $onboardingSiteRead?->status === SiteReadStatus::Completed
            && $proposalIsMissing
            && filled($onboardingSiteRead->error_message);
        $waitingForProposal = $onboardingSiteRead?->status === SiteReadStatus::Completed
            && $proposalIsMissing
            && ! $proposalExtractionFailed;
        $siteReadFailed = $onboardingSiteRead?->status === SiteReadStatus::Failed || $proposalExtractionFailed;

        return [
            'polling' => $onboardingSiteRead?->status === SiteReadStatus::InProgress || $waitingForProposal,
            'proposal' => $pendingProposal === null ? null : [
                'origin' => $onboardingProposal?->is($pendingProposal) ? 'onboarding' : 'settings',
                'href' => $onboardingProposal?->is($pendingProposal)
                    ? route('onboarding.company.proposal-review')
                    : route('company.edit'),
            ],
            'failure' => $siteReadFailed ? [
                'message' => filled($onboardingSiteRead->error_message)
                    ? $onboardingSiteRead->error_message
                    : 'A Leitura do site não foi concluída.',
                'href' => route('company.edit'),
            ] : null,
        ];
    }

    private function companyIsComplete(Team $team): bool
    {
        return collect([
            $team->name,
            $team->segment,
            $team->business_description,
            $team->products_services,
            $team->target_audience,
            $team->business_hours,
            $team->timezone,
        ])->every(fn (mixed $value): bool => filled($value));
    }

    /**
     * @return array{usedBytes: int, limitBytes: int, usedPercentage: float}
     */
    private function storageSummary(Team $team): array
    {
        $usedBytes = $this->quota->usage($team);
        $limitBytes = $this->quota->limit();

        return [
            'usedBytes' => $usedBytes,
            'limitBytes' => $limitBytes,
            'usedPercentage' => round(min(100, ($usedBytes / max(1, $limitBytes)) * 100), 1),
        ];
    }
}
