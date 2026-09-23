<?php

namespace App\WhatsApp\Testes;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class EvolutionGoMediaDownloader
{
    /**
     * Resolve media embedded in, or referenced by, an Evolution Go message.
     *
     * @param  array<string, mixed>  $payload
     * @return array{contents: string, mime_type: string|null, filename: string}|null
     */
    public function download(Channel $channel, array $payload, string $type): ?array
    {
        $data = $this->eventData($payload);
        $body = $this->messageBody($data);
        $mediaKey = $type.'Message';
        $media = is_array($body[$mediaKey] ?? null) ? $body[$mediaKey] : [];
        $containers = [$media, $body, $data, $payload];
        $encoded = $this->firstString($containers, ['base64', 'media', 'mediaData', 'content']);
        $mimeType = $this->firstString($containers, ['mimetype', 'mimeType', 'mime']);
        $filename = $this->firstString($containers, ['fileName', 'filename', 'name']);

        if ($encoded !== null) {
            return $this->fromBase64($encoded, $mimeType, $filename, $type);
        }

        $url = $this->firstString($containers, ['url', 'mediaUrl', 'media_url', 'downloadUrl']);

        if ($url === null) {
            return null;
        }

        return $this->fromUrl($channel, $url, $mimeType, $filename, $type);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function eventData(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        return is_array($data['data'] ?? null) ? $data['data'] : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function messageBody(array $data): array
    {
        $message = $data['message'] ?? [];

        if (! is_array($message)) {
            return [];
        }

        if (isset($message['key']) && is_array($message['message'] ?? null)) {
            return $message['message'];
        }

        return $message;
    }

    /**
     * @param  array<int, array<string, mixed>>  $containers
     * @param  array<int, string>  $keys
     */
    private function firstString(array $containers, array $keys): ?string
    {
        foreach ($containers as $container) {
            foreach ($keys as $key) {
                $value = $container[$key] ?? null;

                if (is_string($value) && filled($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array{contents: string, mime_type: string|null, filename: string}
     */
    private function fromBase64(string $encoded, ?string $mimeType, ?string $filename, string $type): array
    {
        if (Str::startsWith($encoded, 'data:')) {
            [$header, $encoded] = array_pad(explode(',', $encoded, 2), 2, null);
            $mimeType = $mimeType ?? Str::between($header, 'data:', ';base64');
        }

        $contents = base64_decode($encoded, true);

        if ($contents === false) {
            throw new RuntimeException('A mídia do WhatsApp de testes está em um formato inválido.');
        }

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'filename' => $filename ?? $this->fallbackFilename($mimeType, $type),
        ];
    }

    /**
     * @return array{contents: string, mime_type: string|null, filename: string}
     */
    private function fromUrl(
        Channel $channel,
        string $url,
        ?string $mimeType,
        ?string $filename,
        string $type,
    ): array {
        $channel->loadMissing('whatsappTestConnection');
        $connection = $channel->whatsappTestConnection;

        if ($connection === null) {
            throw new RuntimeException('A conexão do WhatsApp de testes não está configurada.');
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = rtrim($connection->service_url, '/').'/'.ltrim($url, '/');
        }

        $request = Http::connectTimeout(3)->timeout(20);

        if (filled($connection->access_token)) {
            $request = $request->withHeader('apikey', $connection->access_token);
        }

        $response = $request->get($url)->throw();
        $resolvedMimeType = $mimeType ?? $this->contentType($response->header('Content-Type'));

        return [
            'contents' => $response->body(),
            'mime_type' => $resolvedMimeType,
            'filename' => $filename ?? $this->fallbackFilename($resolvedMimeType, $type),
        ];
    }

    private function contentType(?string $contentType): ?string
    {
        if ($contentType === null) {
            return null;
        }

        $mimeType = explode(';', $contentType, 2)[0];
        $mimeType = trim($mimeType);

        return $mimeType === '' ? null : $mimeType;
    }

    private function fallbackFilename(?string $mimeType, string $type): string
    {
        $extension = match ($mimeType) {
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => $type,
        };

        return 'whatsapp-media.'.$extension;
    }
}
