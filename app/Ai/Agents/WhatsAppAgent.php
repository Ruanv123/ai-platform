<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SearchKnowledgeBase;
use App\Enums\MessageDirection;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent as ConfiguredAgent;
use App\Models\Message as ConversationMessage;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class WhatsAppAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Create an Agent for a saved WhatsApp atendimento configuration.
     *
     * @param  Collection<int, ConversationMessage>  $conversationMessages
     */
    public function __construct(
        private ConfiguredAgent $configuredAgent,
        private Collection $conversationMessages,
        private AgentInstructionTemplate $instructionTemplate,
        private KnowledgeBaseSearch $knowledgeBaseSearch,
    ) {}

    /**
     * Get the instructions that the Agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructionTemplate->render($this->configuredAgent);
    }

    /**
     * Get the current Contact history as AI conversation messages.
     *
     * @return Collection<int, Message>
     */
    public function messages(): iterable
    {
        return $this->conversationMessages->map(fn (ConversationMessage $message): Message => new Message(
            $message->direction === MessageDirection::Outgoing ? 'assistant' : 'user',
            $message->content ?? "[Mensagem {$message->event_type} recebida sem conteúdo textual.]",
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

        if ($collections->isEmpty()) {
            return [];
        }

        return [new SearchKnowledgeBase(
            $this->configuredAgent->team,
            $collections->modelKeys(),
            $this->knowledgeBaseSearch,
        )];
    }
}
