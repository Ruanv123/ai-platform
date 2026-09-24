<?php

namespace App\WhatsApp;

use App\Enums\ChannelType;
use App\Flows\FlowEngine;
use App\Inbox\AgentConversationService;
use App\Jobs\RespondToWhatsAppMessage;
use App\Jobs\RunFlowTurn;
use App\Models\Channel;
use App\Models\Message;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

final class IncomingMessageResponseDispatcher
{
    /** Seconds to wait for follow-up messages before the response job runs. */
    public const int BUFFER_SECONDS = 10;

    public function __construct(
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
        private readonly FlowEngine $flowEngine,
        private readonly OptOutInterceptor $optOutInterceptor,
        private readonly AgentConversationService $agentConversations,
    ) {}

    /**
     * Start the response flow for a newly persisted incoming Message.
     */
    public function dispatch(Channel $channel, Message $message): void
    {
        $message->loadMissing('contact');
        if ($message->contact !== null
            && $this->agentConversations->recordActivityWhilePaused($message->contact, $channel, $message)) {
            return;
        }

        if (! $this->isAllowedToRespond($channel, $message)) {
            return;
        }

        $channel->loadMissing('agent');
        $isFlowChannel = $this->flowEngine->hasManagedMessageFlows($channel)
            && $this->flowEngine->hasNonTrivialManagedMessageFlows($channel);

        if ($this->optOutInterceptor->intercept($channel, $message)) {
            return;
        }

        if (! $isFlowChannel && ($channel->agent === null || ! $channel->agent->is_active)) {
            return;
        }

        $adapter = $this->outputAdapterFactory->make($channel);
        $this->attempt(function () use ($adapter, $channel, $message): void {
            $adapter->markIncomingMessageAsRead($channel, $message);
        });
        $this->attempt(function () use ($adapter, $channel, $message): void {
            $adapter->sendTypingIndicator($channel, $message);
        });

        if ($isFlowChannel) {
            RunFlowTurn::dispatch($channel->getKey(), $message->contact_id)
                ->delay(now()->addSeconds(self::BUFFER_SECONDS));

            return;
        }

        RespondToWhatsAppMessage::dispatch($channel->getKey(), $message->contact_id)
            ->delay(now()->addSeconds(self::BUFFER_SECONDS));
    }

    /**
     * Limit the WhatsApp test Channel to the phones listed in EVOLUTION_TEST_ALLOWED_PHONES.
     */
    private function isAllowedToRespond(Channel $channel, Message $message): bool
    {
        if ($channel->type !== ChannelType::WhatsAppTest) {
            return true;
        }

        $message->loadMissing('contact');
        $phone = $this->comparablePhone((string) $message->contact?->phone);
        $allowed = array_map(
            $this->comparablePhone(...),
            (array) config('services.evolution.test_allowed_phones', []),
        );

        if (in_array($phone, $allowed, true)) {
            return true;
        }

        Log::info('[WHATSAPP-TEST] response.skipped_phone_not_allowed', [
            'channel_id' => $channel->getKey(),
            'contact_id' => $message->contact_id,
        ]);

        return false;
    }

    /**
     * Keep only digits and drop the Brazilian mobile ninth digit, which WhatsApp JIDs may omit.
     */
    private function comparablePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) === 13 && str_starts_with($digits, '55')
            ? substr($digits, 0, 4).substr($digits, 5)
            : $digits;
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
