<?php

namespace App\Ai;

use App\Ai\Tools\BookAppointment;
use App\Ai\Tools\CancelAppointment;
use App\Ai\Tools\ConsultAvailableTimes;
use App\Ai\Tools\ConsultCrmCard;
use App\Ai\Tools\GuaranteeCrmDeal;
use App\Ai\Tools\ListAppointments;
use App\Ai\Tools\MoveCrmDealStage;
use App\Ai\Tools\RegisterCrmNote;
use App\Ai\Tools\ReturnToFlow;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Ai\Tools\SearchProducts;
use App\Ai\Tools\SendPurchaseLink;
use App\Crm\CrmCardLookup;
use App\Crm\CrmOperationDispatcher;
use App\Ecommerce\ProductCatalogSearch;
use App\Ecommerce\ProductCatalogTriage;
use App\Enums\AgentObjective;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent as ConfiguredAgent;
use App\Models\AgentVersion;
use App\Models\Channel;
use App\Models\Contact;
use App\Scheduling\SchedulingService;
use Illuminate\Support\Collection;

class AgentToolFactory
{
    public function __construct(
        private KnowledgeBaseSearch $knowledgeBaseSearch,
        private ProductCatalogSearch $productCatalogSearch,
        private ?SchedulingService $scheduling = null,
        private ?CrmCardLookup $crmCardLookup = null,
    ) {}

    public function make(
        ConfiguredAgent $agent,
        ?Contact $contact,
        AgentToolMode $mode,
        array|Collection $conversationMessages,
        ?Channel $channel = null,
        ?ReturnToFlow $returnToFlow = null,
        ?string $crmLookupPhone = null,
    ): array {
        $tools = [];
        $collections = $agent->collections;

        if ($collections->isNotEmpty()) {
            $tools[] = new SearchKnowledgeBase(
                $agent->team,
                $collections->modelKeys(),
                $this->knowledgeBaseSearch,
            );
        }

        $team = $agent->team;
        $shopConnected = $team->shops()->exists();
        $publishedVersion = $mode === AgentToolMode::Real ? $agent->publishedVersion() : null;

        if ($this->shouldSearchProducts($agent, $mode, $publishedVersion, $shopConnected)) {
            $tools[] = new SearchProducts(
                $team,
                $this->productCatalogSearch,
                new ProductCatalogTriage,
                $agent,
                $contact,
                $conversationMessages,
            );
        }

        if ($this->shouldSendPurchaseLink($agent, $mode, $publishedVersion, $shopConnected)) {
            $tools[] = new SendPurchaseLink($team, $agent, $contact, $channel);
        }

        $calendars = $agent->schedulableCalendars;

        if ($calendars->isNotEmpty()) {
            $scheduling = $this->scheduling ?? app(SchedulingService::class);
            $simulate = $mode === AgentToolMode::Simulated;
            $tools[] = new ConsultAvailableTimes($team, $calendars, $scheduling);
            $tools[] = new BookAppointment($contact, $calendars, $scheduling, $simulate);
            $tools[] = new ListAppointments($contact, $scheduling, $simulate);
            $tools[] = new CancelAppointment($contact, $scheduling, $simulate);
        }

        $crmIntegration = $team->integrations()->where('type', 'crm')->where('status', 'connected')->first();
        $crmConnected = $crmIntegration !== null;
        $crmActionsDefaultEnabled = $crmConnected && in_array(
            $mode === AgentToolMode::Real && $publishedVersion !== null
                ? $publishedVersion->objectiveValue()
                : $agent->objective,
            [AgentObjective::Sales, AgentObjective::Qualification],
            true,
        );
        $crmLookupEnabled = $agent->crm_lookup_enabled ?? (
            $crmConnected && in_array($agent->objective, [AgentObjective::Sales, AgentObjective::Qualification], true)
        );

        if ($crmLookupEnabled && $crmConnected) {
            $tools[] = new ConsultCrmCard(
                $team,
                $contact,
                $this->crmCardLookup ?? app(CrmCardLookup::class),
                $mode === AgentToolMode::Simulated ? $crmLookupPhone : null,
            );
        }

        $crmTools = $mode === AgentToolMode::Real ? ($publishedVersion?->crm_tools ?? []) : [];
        $ensureDealEnabled = $crmConnected && ($crmTools['ensure_deal_enabled'] ?? $agent->crm_ensure_deal_enabled ?? $crmActionsDefaultEnabled) === true;
        $moveDealEnabled = $crmConnected && ($crmTools['move_deal_enabled'] ?? $agent->crm_move_deal_enabled ?? $crmActionsDefaultEnabled) === true;
        $registerNoteEnabled = $crmConnected && ($crmTools['register_note_enabled'] ?? $agent->crm_register_note_enabled ?? $crmActionsDefaultEnabled) === true;
        $pipelines = $crmIntegration?->crm_metadata['pipelines'] ?? [];
        $firstPipeline = $pipelines[0] ?? null;
        $pipelineId = $crmTools['pipeline_id'] ?? $agent->crm_pipeline_id ?? ($firstPipeline['id'] ?? null);
        $selectedPipeline = collect($pipelines)->first(fn (array $pipeline): bool => (string) ($pipeline['id'] ?? '') === (string) $pipelineId);
        $firstStageId = collect($selectedPipeline['stages'] ?? [])
            ->first(fn (array $stage): bool => ! in_array(strtolower((string) ($stage['status'] ?? '')), ['won', 'lost'], true))['id'] ?? null;
        $initialStageId = $crmTools['initial_stage_id'] ?? $agent->crm_initial_stage_id ?? $firstStageId;
        $allowedStages = $this->allowedStages(
            $crmTools['allowed_stages'] ?? $agent->crm_allowed_stages ?? [],
            $crmIntegration?->crm_metadata ?? [],
            is_string($pipelineId) ? $pipelineId : null,
        );

        if ($ensureDealEnabled && is_string($pipelineId) && $pipelineId !== '' && is_string($initialStageId) && $initialStageId !== '') {
            $tools[] = new GuaranteeCrmDeal(
                $team,
                $agent,
                $contact,
                app(CrmOperationDispatcher::class),
                $pipelineId,
                $initialStageId,
                $mode === AgentToolMode::Simulated,
                $mode === AgentToolMode::Simulated ? $crmLookupPhone : null,
            );
        }

        if ($moveDealEnabled && is_string($pipelineId) && $pipelineId !== '' && $allowedStages !== []) {
            $tools[] = new MoveCrmDealStage(
                $team,
                $agent,
                $contact,
                app(CrmOperationDispatcher::class),
                $pipelineId,
                $allowedStages,
                $mode === AgentToolMode::Simulated,
                $mode === AgentToolMode::Simulated ? $crmLookupPhone : null,
            );
        }

        if ($registerNoteEnabled) {
            $tools[] = new RegisterCrmNote(
                $team,
                $agent,
                $contact,
                app(CrmOperationDispatcher::class),
                is_string($pipelineId) ? $pipelineId : '',
                $mode === AgentToolMode::Simulated,
                $mode === AgentToolMode::Simulated ? $crmLookupPhone : null,
            );
        }

        if ($mode === AgentToolMode::Real && $returnToFlow !== null) {
            $tools[] = $returnToFlow;
        }

        return $tools;
    }

    private function shouldSearchProducts(
        ConfiguredAgent $agent,
        AgentToolMode $mode,
        ?AgentVersion $publishedVersion,
        bool $shopConnected,
    ): bool {
        if ($mode === AgentToolMode::Simulated) {
            return $agent->shouldSearchProducts($shopConnected);
        }

        return $publishedVersion?->shouldSearchProducts($shopConnected, $agent->shop_tools_enabled) ?? false;
    }

    private function shouldSendPurchaseLink(
        ConfiguredAgent $agent,
        AgentToolMode $mode,
        ?AgentVersion $publishedVersion,
        bool $shopConnected,
    ): bool {
        if ($mode === AgentToolMode::Simulated) {
            return $agent->shouldSendPurchaseLink($shopConnected);
        }

        return $publishedVersion?->shouldSendPurchaseLink($shopConnected, $agent->shop_tools_enabled) ?? false;
    }

    private function allowedStages(array $configuredStages, array $metadata, ?string $pipelineId): array
    {
        if ($pipelineId === null) {
            return [];
        }

        $stages = collect($metadata['pipelines'] ?? [])
            ->first(fn (array $pipeline): bool => (string) ($pipeline['id'] ?? '') === $pipelineId)['stages'] ?? [];

        return collect($configuredStages)
            ->filter(fn (array $allowedStage): bool => collect($stages)->contains(
                fn (array $stage): bool => (string) ($stage['id'] ?? '') === (string) ($allowedStage['stage_id'] ?? '')
                    && ! in_array(strtolower((string) ($stage['status'] ?? '')), ['won', 'lost'], true),
            ))
            ->map(fn (array $allowedStage): array => [
                ...$allowedStage,
                'name' => collect($stages)->firstWhere('id', $allowedStage['stage_id'])['name'] ?? $allowedStage['stage_id'],
            ])
            ->values()
            ->all();
    }
}
