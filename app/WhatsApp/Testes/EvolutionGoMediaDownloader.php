<?php

namespace App\WhatsApp\Testes;

use App\Models\Channel;
use RuntimeException;

final class EvolutionGoMediaDownloader
{
    /**
     * Read the media Evolution Go embeds in a Message event (`data.Message.base64`, since WEBHOOK_FILES is on).
     *
     * The `URL` in the message points at WhatsApp's encrypted CDN and is never fetched.
     *
     * @param  array<string, mixed>  $payload
     * @return array{contents: string, mime_type: string|null, filename: string}|null
     */
    public function download(Channel $channel, array $payload, string $type): ?array
    {
        $encoded = data_get($payload, 'data.Message.base64');

        if (! is_string($encoded) || blank($encoded)) {
            return null;
        }

        $contents = base64_decode($encoded, true);

        if ($contents === false) {
            throw new RuntimeException('A mídia do WhatsApp de testes está em um formato inválido.');
        }

        $media = data_get($payload, "data.Message.{$type}Message");
        $mimeType = is_array($media) && is_string($media['mimetype'] ?? null)
            ? trim(explode(';', $media['mimetype'], 2)[0])
            : null;
        $filename = is_array($media) && is_string($media['fileName'] ?? null) && filled($media['fileName'])
            ? $media['fileName']
            : null;

        return [
            'contents' => $contents,
            'mime_type' => filled($mimeType) ? $mimeType : null,
            'filename' => $filename ?? $this->fallbackFilename($mimeType, $type),
        ];
    }

    private function fallbackFilename(?string $mimeType, string $type): string
    {
        $extension = match ($mimeType) {
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'video/3gpp' => '3gp',
            'video/mp4' => 'mp4',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => $type,
        };

        return 'whatsapp-media.'.$extension;
    }
}
