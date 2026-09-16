<?php

namespace App\Http\Controllers;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\DocumentStatus;
use App\Knowledge\DocumentStorageQuota;
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
        $hasLinkedAgent = $team->channels()
            ->where('type', ChannelType::WhatsApp->value)
            ->whereNotNull('agent_id')
            ->whereHas('agent')
            ->exists();

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
                    'href' => route('channels.index'),
                ],
                [
                    'key' => 'agent_linked',
                    'label' => 'Agente vinculado ao WhatsApp',
                    'description' => 'Escolha qual Agente responderá no WhatsApp.',
                    'completed' => $hasLinkedAgent,
                    'href' => route('channels.index'),
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
            ],
        ]);
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
