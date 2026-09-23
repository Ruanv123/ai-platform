<?php

namespace App\Jobs;

use App\Ai\Agents\WhatsAppAgent;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use App\WhatsApp\WhatsAppResponseFailureHandler;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class RespondToWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int RETRY_WINDOW_SECONDS = 10 * 60;

    public int $tries = 5;

    public int $maxExceptions = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public string|int $channelId,
        public int $contactId,
    ) {
        $this->retryDeadline = CarbonImmutable::now()->addSeconds(self::RETRY_WINDOW_SECONDS);
    }

    public CarbonImmutable $retryDeadline;

    public function retryUntil(): DateTimeInterface
    {
        return $this->retryDeadline;
    }

    public function failed(?Throwable $exception): void
    {
        app(WhatsAppResponseFailureHandler::class)->handle(
            $this->channelId,
            $this->contactId,
            $exception,
        );
    }

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
        WhatsAppCustomerCareWindow $customerCareWindow,
    ): void {
        $channel = Channel::query()
            ->with(['agent.collections', 'agent.schedulableCalendars.integration'])
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
            null,
            $contact,
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

        if ($customerCareWindow->hasExpired($channel, $lastPendingMessage)) {
            $this->recordUndeliveredResponse($channel, $contact, $content, $pendingMessages);

            return;
        }

        $outputAdapterFactory->make($channel)->sendText($channel, $contact, $content);
        $this->markAsResponded($pendingMessages);
    }

    /**
     * Register a response that was generated after Meta's customer-care window closed.
     *
     * @param  Collection<int, Message>  $pendingMessages
     */
    private function recordUndeliveredResponse(
        Channel $channel,
        Contact $contact,
        string $content,
        Collection $pendingMessages,
    ): void {
        Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => 'text',
            'provider_message_id' => null,
            'content' => $content,
            'delivery_status' => MessageDeliveryStatus::Failed,
        ]);

        $this->markAsResponded($pendingMessages);
    }

    /**
     * Mark the incoming Messages covered by this response flow as handled.
     *
     * @param  Collection<int, Message>  $messages
     */
    private function markAsResponded(Collection $messages): void
    {
        Message::query()
            ->whereKey($messages->modelKeys())
            ->update(['is_responded' => true]);
    }

    private function promptFor(Message $message): string
    {
        return $message->content
            ?? "O Contato enviou uma mensagem do tipo {$message->payload_type}. Responda orientando-o de forma útil e clara.";
    }
}
