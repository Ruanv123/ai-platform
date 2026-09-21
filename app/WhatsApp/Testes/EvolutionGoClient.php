<?php

namespace App\WhatsApp\Testes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EvolutionGoClient
{
    /**
     * Create a new instance on the platform's shared Evolution Go server.
     *
     * @return array{id: string, token: string}
     */
    public function createInstance(string $name): array
    {
        $response = $this->send('post', 'instance/create', ['name' => $name]);

        $id = $response->json('data.id');
        $token = $response->json('data.token');

        if (! is_string($id) || blank($id) || ! is_string($token) || blank($token)) {
            throw new RuntimeException('O Evolution Go não retornou os dados da instância criada.');
        }

        return ['id' => $id, 'token' => $token];
    }

    /**
     * Register our webhook and start the pairing session for the instance.
     */
    public function connect(string $instanceId, string $webhookUrl): void
    {
        $this->send('post', 'instance/connect', [
            'webhookUrl' => $webhookUrl,
            'subscribe' => ['QRCODE', 'CONNECTION', 'MESSAGE', 'SEND_MESSAGE', 'READ_RECEIPT'],
            'immediate' => true,
        ], ['instanceId' => $instanceId]);
    }

    /**
     * Send a request to the platform's Evolution Go server, translating any failure to Portuguese.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $path, array $payload, array $headers = []): Response
    {
        $baseUrl = config('services.evolution.base_url');
        $apiKey = config('services.evolution.api_key');

        if (blank($baseUrl) || blank($apiKey)) {
            throw new RuntimeException('O Evolution Go ainda não está configurado nesta plataforma. Defina EVOLUTION_API_URL e EVOLUTION_API_KEY.');
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withHeader('apikey', $apiKey)
                ->withHeaders($headers)
                ->{$method}(rtrim((string) $baseUrl, '/').'/'.trim($path, '/'), $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Não foi possível conectar ao Evolution Go. Verifique se o serviço está no ar.');
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->translateError($response->json()));
        }

        return $response;
    }

    /**
     * Translate an Evolution Go error body into a message in Portuguese.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function translateError(?array $body): string
    {
        $message = data_get($body, 'message') ?? data_get($body, 'error.message');

        return is_string($message) && filled($message)
            ? "O Evolution Go recusou a operação: {$message}"
            : 'O Evolution Go recusou a operação. Verifique o nome escolhido e tente novamente.';
    }
}
