<?php

namespace App\WhatsApp\Oficial;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MetaMediaDownloader
{
    public function __construct(private readonly MetaGraphApiResponseHandler $responseHandler) {}

    /**
     * Download media from Meta's two-step media API.
     *
     * @return array{contents: string, mime_type: string|null, filename: string}
     */
    public function download(
        Channel $channel,
        string $mediaId,
        ?string $filename = null,
        ?string $mimeType = null,
    ): array {
        $channel->loadMissing('whatsappPhoneNumber.whatsappAccount');
        $account = $channel->whatsappPhoneNumber?->whatsappAccount;

        if ($account === null) {
            throw new RuntimeException('A conta oficial do WhatsApp não está configurada.');
        }

        $graphUrl = rtrim((string) config('services.whatsapp.graph_api_url', 'https://graph.facebook.com'), '/');
        $graphVersion = trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/');
        $metadata = Http::withToken($account->access_token)
            ->connectTimeout(3)
            ->timeout(10)
            ->get($graphUrl.'/'.$graphVersion.'/'.rawurlencode($mediaId));
        $this->responseHandler->markChannelUnavailableForInvalidToken($metadata, $channel);
        $metadata->throw();
        $mediaUrl = $metadata->json('url');

        if (! is_string($mediaUrl) || blank($mediaUrl)) {
            throw new RuntimeException('O provedor não retornou o endereço da mídia.');
        }

        $response = Http::withToken($account->access_token)
            ->connectTimeout(3)
            ->timeout(20)
            ->get($mediaUrl);
        $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel);
        $response->throw();
        $resolvedMimeType = $mimeType
            ?? $this->stringValue($metadata->json('mime_type'))
            ?? $this->contentType($response->header('Content-Type'));

        return [
            'contents' => $response->body(),
            'mime_type' => $resolvedMimeType,
            'filename' => $filename ?? $this->fallbackFilename($resolvedMimeType),
        ];
    }

    private function contentType(?string $contentType): ?string
    {
        if ($contentType === null) {
            return null;
        }

        $mimeType = trim(explode(';', $contentType, 2)[0]);

        return $mimeType === '' ? null : $mimeType;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && filled($value) ? (string) $value : null;
    }

    private function fallbackFilename(?string $mimeType): string
    {
        $extension = match ($mimeType) {
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'video/3gpp' => '3gp',
            'video/mp4' => 'mp4',
            default => 'bin',
        };

        return 'whatsapp-media.'.$extension;
    }
}
