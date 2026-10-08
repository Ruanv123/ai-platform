<?php

namespace App\Inbox;

use App\Models\Message;
use App\Models\WhatsAppTemplate;

final readonly class AgentTemplateDelivery
{
    private function __construct(
        public bool $sent,
        public ?string $reason,
        public ?WhatsAppTemplate $template,
        public ?Message $message,
    ) {}

    public static function sent(WhatsAppTemplate $template, Message $message): self
    {
        return new self(true, null, $template, $message);
    }

    public static function blocked(string $reason, ?WhatsAppTemplate $template = null, ?Message $message = null): self
    {
        return new self(false, $reason, $template, $message);
    }

    public function isNotConfigured(): bool
    {
        return $this->reason === 'template_not_configured';
    }
}
