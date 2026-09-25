<?php

namespace App\WhatsApp\Profiles;

final readonly class WhatsAppProfileCapabilities
{
    public function __construct(
        public array $readableFields,
        public array $editableFields,
        public bool $canRequestDisplayName,
    ) {}

    public function toArray(): array
    {
        return [
            'readableFields' => $this->readableFields,
            'editableFields' => $this->editableFields,
            'canRequestDisplayName' => $this->canRequestDisplayName,
        ];
    }
}
