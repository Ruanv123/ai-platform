<?php

namespace App\Ai\Agents;

use App\Ai\Tools\BookAppointment;
use App\Ai\Tools\CancelAppointment;
use App\Ai\Tools\ConsultAvailableTimes;
use App\Ai\Tools\ListAppointments;
use App\Ai\Tools\RescheduleAppointment;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Ai\Tools\SendWhatsAppTemplate;
use App\Enums\AgentTemplateUsage;
use App\Enums\MessageDirection;
use App\Inbox\AgentTemplateSender;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent as ConfiguredAgent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message as ConversationMessage;
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
        private ?Contact $contact = null,
        private ?SchedulingService $scheduling = null,
        private ?Channel $channel = null,
    ) {}

    /**
     * Get the instructions that the Agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $instructions = $this->instructionTemplate->render($this->configuredAgent);
        if ($this->contact === null || $this->configuredAgent->schedulableCalendars->isEmpty()) {
            return $instructions;
        }

        $timezone = $this->configuredAgent->team->timezone ?: 'America/Sao_Paulo';
        $appointments = $this->contact->appointments()
            ->where('team_id', $this->configuredAgent->team_id)
            ->whereIn('schedulable_calendar_id', $this->configuredAgent->schedulableCalendars->modelKeys())
            ->where('status', 'booked')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($appointment): string => '- '.$appointment->starts_at->setTimezone($timezone)->format('d/m/Y H:i')
                .' até '.$appointment->ends_at->setTimezone($timezone)->format('H:i'));

        if ($appointments->isNotEmpty()) {
            $instructions .= "\n\n## Compromissos já registrados deste Contato\n".$appointments->implode("\n")
                ."\nEstes registros são persistentes, não apenas promessas do histórico. Use listar_compromissos para confirmar o estado atual no Google antes de responder ou remarcar. Não negue a existência deles sem verificar a Agenda.";
        }

        return $instructions;
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
            $message->content ?? "[Mensagem {$message->payload_type} recebida sem conteúdo textual.]",
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
        $calendars = $this->configuredAgent->schedulableCalendars;
        if ($calendars->isNotEmpty()) {
            $scheduling = $this->scheduling ?? app(SchedulingService::class);
            $tools = [
                ...$tools,
                new ConsultAvailableTimes($this->configuredAgent->team, $calendars, $scheduling),
                new BookAppointment($this->contact, $calendars, $scheduling),
                new ListAppointments($this->contact, $scheduling),
                new CancelAppointment($this->contact, $scheduling),
                new RescheduleAppointment($this->contact, $calendars, $scheduling),
            ];
        }

        if ($this->channel !== null && $this->contact !== null) {
            $sender = app(AgentTemplateSender::class);
            $templates = $sender->templatesFor($this->configuredAgent, $this->channel, AgentTemplateUsage::OnDemand);
            if ($templates->isNotEmpty()) {
                $tools[] = new SendWhatsAppTemplate($this->configuredAgent, $this->channel, $this->contact, $templates, $sender);
            }
        }

        return $tools;
    }
}
