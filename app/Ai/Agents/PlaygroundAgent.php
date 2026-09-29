<?php

namespace App\Ai\Agents;

use App\Ai\Tools\BookAppointment;
use App\Ai\Tools\CancelAppointment;
use App\Ai\Tools\ConsultAvailableTimes;
use App\Ai\Tools\ListAppointments;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Ai\Tools\SearchProducts;
use App\Ai\Tools\SendPurchaseLink;
use App\Ecommerce\ProductCatalogSearch;
use App\Ecommerce\ProductCatalogTriage;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent as ConfiguredAgent;
use App\Scheduling\SchedulingService;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class PlaygroundAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Create an agent from the current, non-persisted platform configuration.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationMessages
     */
    public function __construct(
        private ConfiguredAgent $configuredAgent,
        private array $conversationMessages,
        private AgentInstructionTemplate $instructionTemplate,
        private KnowledgeBaseSearch $knowledgeBaseSearch,
        private ProductCatalogSearch $productCatalogSearch,
        private ?SchedulingService $scheduling = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructionTemplate->render($this->configuredAgent);
    }

    /**
     * Get the current, client-owned conversation history.
     *
     * @return Collection<int, Message>
     */
    public function messages(): iterable
    {
        return collect($this->conversationMessages)
            ->map(fn (array $message): Message => new Message(
                $message['role'],
                $message['content'],
            ));
    }

    /**
     * Get the knowledge search tool when this Agent has selected Collections.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        $collections = $this->configuredAgent->collections;

        $tools = [];
        if ($collections->isNotEmpty()) {
            $tools[] = new SearchKnowledgeBase(
                $this->configuredAgent->team,
                $collections->modelKeys(),
                $this->knowledgeBaseSearch,
            );
        }
        $team = $this->configuredAgent->team;
        if ($this->configuredAgent->shouldSearchProducts($team->shops()->exists())) {
            $tools[] = new SearchProducts(
                $team,
                $this->productCatalogSearch,
                new ProductCatalogTriage,
                $this->configuredAgent,
                null,
                $this->conversationMessages,
            );
        }
        if ($this->configuredAgent->shouldSendPurchaseLink($team->shops()->exists())) {
            $tools[] = new SendPurchaseLink($team, $this->configuredAgent, null, null);
        }

        $calendars = $this->configuredAgent->schedulableCalendars;
        if ($calendars->isNotEmpty()) {
            $scheduling = $this->scheduling ?? app(SchedulingService::class);
            $tools = [
                ...$tools,
                new ConsultAvailableTimes($this->configuredAgent->team, $calendars, $scheduling),
                new BookAppointment(null, $calendars, $scheduling, true),
                new ListAppointments(null, $scheduling, true),
                new CancelAppointment(null, $scheduling, true),
            ];
        }

        return $tools;
    }
}
