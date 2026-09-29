<?php

namespace App\WhatsApp\Output;

final readonly class StoredWhatsAppMedia
{
    public function __construct(
        public string $path,
        public string $filename,
        public string $mimeType,
        public int $size,
    ) {}
}
