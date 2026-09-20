<?php

namespace App\Jobs;

use App\Ai\Agents\WhatsAppAgent;
use App\Enums\MessageDirection;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class RespondToWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public int $channelId,
        public int $contactId,
    ) {}

    /**
     * Serialize response flows for one Contact and Channel.
     *
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("{$this->channelId}:{$this->contactId}"))
                ->releaseAfter(1)
                ->expireAfter(120),
        ];
    }

    public function handle(
        AgentInstructionTemplate $instructionTemplate,
        KnowledgeBaseSearch $knowledgeBaseSearch,
        WhatsAppOutputAdapterFactory $outputAdapterFactory,
    ): void {
        $channel = Channel::query()
            ->with('agent.collections')
            ->find($this->channelId);

        if ($channel === null || $channel->agent === null || ! $channel->agent->is_active) {
            return;
        }

        $contact = Contact::query()
            ->whereKey($this->contactId)
            ->where('team_id', $channel->team_id)
            ->first();

        if ($contact === null) {
            return;
        }

        $pendingMessages = Message::query()
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->where('is_responded', false)
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        if ($pendingMessages->isEmpty()) {
            return;
        }

        $lastPendingMessage = $pendingMessages->last();
        $history = $contact->history($channel)
            ->reject(fn (Message $message): bool => $message->is($lastPendingMessage))
            ->values();
        $agent = new WhatsAppAgent(
            $channel->agent,
            $history,
            $instructionTemplate,
            $knowledgeBaseSearch,
        );
        $response = $agent->prompt(
            $this->promptFor($lastPendingMessage),
            provider: config('ai.playground.provider'),
            model: config('ai.playground.model'),
            timeout: (int) config('ai.playground.timeout', 60),
        );
        $content = trim($response->text);

        if ($content === '') {
            throw new RuntimeException('O Agente não retornou conteúdo para enviar ao Contato.');
        }

        $outputAdapterFactory->make($channel)->sendText($channel, $contact, $content);
        Message::query()
            ->whereKey($pendingMessages->modelKeys())
            ->update(['is_responded' => true]);
    }

    private function promptFor(Message $message): string
    {
        return $message->content
            ?? "O Contato enviou uma mensagem do tipo {$message->event_type}. Responda orientando-o de forma útil e clara.";
    }
}
