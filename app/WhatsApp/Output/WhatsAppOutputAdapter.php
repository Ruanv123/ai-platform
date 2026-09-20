<?php

namespace App\WhatsApp\Output;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;

interface WhatsAppOutputAdapter
{
    /**
     * Send one text response and register the outgoing Message.
     */
    public function sendText(Channel $channel, Contact $contact, string $content): Message;

    /**
     * Tell the provider that an incoming Message was seen by the Agent.
     */
    public function markIncomingMessageAsRead(Channel $channel, Message $message): void;

    /**
     * Show the provider's typing indicator for an incoming Message.
     */
    public function sendTypingIndicator(Channel $channel, Message $message): void;
}
