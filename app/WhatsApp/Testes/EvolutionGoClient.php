<?php

namespace App\WhatsApp\Testes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class EvolutionGoClient
{
    /**
     * Create a new instance on the platform's shared Evolution Go server.
     */
    public function createInstance(string $name): string
    {
        $token = Str::uuid()->toString();

        $this->send('post', 'instance/create', [
            'name' => $name,
            'token' => $token,
        ], (string) config('services.evolution.api_key'));

        return $token;
    }

    /**
     * Register our webhook and start the pairing session for the instance.
     */
    public function connect(string $instanceToken, string $webhookUrl): void
    {
        $this->send('post', 'instance/connect', [
            'webhookUrl' => $webhookUrl,
            'subscribe' => ['QRCODE', 'CONNECTION', 'MESSAGE', 'SEND_MESSAGE', 'READ_RECEIPT'],
            'immediate' => true,
        ], $instanceToken);
    }

    /**
     * Whether the instance's WhatsApp session is already paired (logged in).
     */
    public function isLoggedIn(string $instanceToken): bool
    {
        return data_get($this->send('get', 'instance/status', [], $instanceToken)->json(), 'data.LoggedIn') === true;
    }

    /**
     * Fetch the pairing QR code currently valid for the instance, as an image data URI.
     *
     * Evolution Go rotates the QR code every few seconds, so it must be read live instead of stored.
     * Returns null while the server has no QR code to show (e.g. still generating or already paired).
     */
    public function qrcode(string $instanceToken): ?string
    {
        $body = $this->send('get', 'instance/qr', [], $instanceToken)->json();

        foreach (['data.Qrcode', 'data.qrcode', 'data.qrCode', 'data.QRCode', 'data.base64', 'Qrcode', 'qrcode'] as $path) {
            $value = data_get($body, $path);

            if (is_string($value) && filled($value)) {
                return $this->imageDataUri($value);
            }
        }

        return null;
    }

    /**
     * Normalize a raw base64 or data URI image into a data URI, rejecting anything that isn't an image.
     */
    private function imageDataUri(string $value): ?string
    {
        $base64 = preg_replace('/\Adata:image\/[a-z+.-]+;base64,/i', '', trim($value)) ?? '';
        $base64 = preg_replace('/\s+/', '', $base64) ?? '';
        $image = base64_decode($base64, true);

        if ($image === false) {
            return null;
        }

        $mimeType = match (true) {
            str_starts_with($image, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($image, "\xff\xd8\xff") => 'image/jpeg',
            str_starts_with($image, 'GIF87a'), str_starts_with($image, 'GIF89a') => 'image/gif',
            default => null,
        };

        return $mimeType === null ? null : "data:{$mimeType};base64,{$base64}";
    }

    /**
     * Send a request to the platform's Evolution Go server, translating any failure to Portuguese.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload, string $apiKey): Response
    {
        $baseUrl = config('services.evolution.base_url');

        if (blank($baseUrl) || blank($apiKey)) {
            throw new RuntimeException('O Evolution Go ainda não está configurado nesta plataforma. Defina EVOLUTION_API_URL e EVOLUTION_API_KEY.');
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withHeader('apikey', $apiKey)
                ->{$method}(rtrim((string) $baseUrl, '/').'/'.trim($path, '/'), $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Não foi possível conectar ao Evolution Go. Verifique se o serviço está no ar.');
        }

        if (! $response->successful()) {
            $body = $response->json();

            throw new RuntimeException($this->translateError(
                is_array($body) ? $body : null,
                $response->status(),
            ));
        }

        return $response;
    }

    /**
     * Translate an Evolution Go error body into a message in Portuguese.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function translateError(?array $body, int $status): string
    {
        $message = data_get($body, 'message')
            ?? data_get($body, 'error.message')
            ?? data_get($body, 'error');

        return is_string($message) && filled($message)
            ? "O Evolution Go recusou a operação: {$message}"
            : "O Evolution Go recusou a operação (HTTP {$status}). Revise os dados enviados e a configuração da integração.";
    }
}
