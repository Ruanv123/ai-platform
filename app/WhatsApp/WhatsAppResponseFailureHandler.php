<?php

namespace App\WhatsApp;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Support\Str;
use Throwable;

final class WhatsAppResponseFailureHandler
{
    public const string GENERIC_FAILURE_NOTICE = 'Não foi possível concluir seu atendimento neste momento. Tente novamente mais tarde.';

    public function __construct(
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
        private readonly WhatsAppCustomerCareWindow $customerCareWindow,
    ) {}

    /**
     * Record the terminal error and notify the Contact when the provider is still reachable.
     */
    public function handle(string|int $channelId, int $contactId, ?Throwable $exception): void
    {
        $channel = Channel::query()->find($channelId);

        if ($channel === null) {
            return;
        }

        $channel->update([
            'last_error' => $this->errorMessage($exception),
        ]);

        $contact = Contact::query()
            ->whereKey($contactId)
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

        try {
            if ($this->customerCareWindow->hasExpired($channel, $pendingMessages->last())) {
                Message::create([
                    'team_id' => $channel->team_id,
                    'contact_id' => $contact->getKey(),
                    'channel_id' => $channel->getKey(),
                    'direction' => MessageDirection::Outgoing,
                    'payload_type' => 'text',
                    'provider_message_id' => null,
                    'content' => self::GENERIC_FAILURE_NOTICE,
                    'delivery_status' => MessageDeliveryStatus::Failed,
                    'failure_reason' => 'agent_response_failed',
                ]);
            } else {
                $this->outputAdapterFactory
                    ->make($channel)
                    ->sendText($channel, $contact, self::GENERIC_FAILURE_NOTICE);
            }
        } catch (Throwable $notificationException) {
            report($notificationException);
        } finally {
            Message::query()
                ->whereKey($pendingMessages->modelKeys())
                ->update(['is_responded' => true]);
        }
    }

    private function errorMessage(?Throwable $exception): string
    {
        $message = trim((string) $exception?->getMessage());

        return filled($message)
            ? Str::limit($message, 1000)
            : 'A resposta do Agente falhou após as tentativas disponíveis.';
    }
}
