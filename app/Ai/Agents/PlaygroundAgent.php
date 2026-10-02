<?php

namespace App\Ai\Agents;

use App\Ai\AgentToolFactory;
use App\Ai\AgentToolMode;
use App\Ecommerce\ProductCatalogSearch;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent as ConfiguredAgent;
use App\Models\Contact;
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
        private ?string $crmLookupPhone = null,
        private ?Contact $contact = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructionTemplate->render($this->configuredAgent, $this->contact);
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
        return (new AgentToolFactory(
            $this->knowledgeBaseSearch,
            $this->productCatalogSearch,
            $this->scheduling,
        ))->make(
            $this->configuredAgent,
            null,
            AgentToolMode::Simulated,
            $this->conversationMessages,
            crmLookupPhone: $this->crmLookupPhone,
        );
    }
}
