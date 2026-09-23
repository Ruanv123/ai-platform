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
