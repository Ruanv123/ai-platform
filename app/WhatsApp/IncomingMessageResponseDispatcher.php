<?php

namespace App\WhatsApp;

use App\Jobs\RespondToWhatsAppMessage;
use App\Models\Channel;
use App\Models\Message;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Throwable;

final class IncomingMessageResponseDispatcher
{
    public function __construct(
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
    ) {}

    /**
     * Start the response flow for a newly persisted incoming Message.
     */
    public function dispatch(Channel $channel, Message $message): void
    {
        $channel->loadMissing('agent');

        if ($channel->agent === null || ! $channel->agent->is_active) {
            return;
        }

        $adapter = $this->outputAdapterFactory->make($channel);
        $this->attempt(function () use ($adapter, $channel, $message): void {
            $adapter->markIncomingMessageAsRead($channel, $message);
        });
        $this->attempt(function () use ($adapter, $channel, $message): void {
            $adapter->sendTypingIndicator($channel, $message);
        });

        RespondToWhatsAppMessage::dispatch($channel->getKey(), $message->contact_id)
            ->delay(now()->addSeconds(5));
    }

    /**
     * Keep a provider notification from preventing the response flow.
     */
    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
