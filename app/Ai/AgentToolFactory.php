<?php

namespace App\Ai;

use App\Ai\Tools\BookAppointment;
use App\Ai\Tools\CancelAppointment;
use App\Ai\Tools\ConsultAvailableTimes;
use App\Ai\Tools\ListAppointments;
use App\Ai\Tools\ReturnToFlow;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Ai\Tools\SearchProducts;
use App\Ai\Tools\SendPurchaseLink;
use App\Ecommerce\ProductCatalogSearch;
use App\Ecommerce\ProductCatalogTriage;
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
    ) {}

    public function make(
        ConfiguredAgent $agent,
        ?Contact $contact,
        AgentToolMode $mode,
        array|Collection $conversationMessages,
        ?Channel $channel = null,
        ?ReturnToFlow $returnToFlow = null,
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
}
