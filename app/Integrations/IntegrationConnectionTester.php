<?php

namespace App\Integrations;

use App\Models\Integration;
use Throwable;

final class IntegrationConnectionTester
{
    public function __construct(private readonly IntegrationClient $client) {}

    /** @return array{connected: bool, message: string} */
    public function test(Integration $integration): array
    {
        $baseUrl = $integration->baseUrl();
        if ($baseUrl === null) {
            return ['connected' => false, 'message' => 'Informe a URL base para testar esta Integração.'];
        }

        $path = (string) $integration->credential('test_path', '/');

        try {
            $response = $this->client->request($integration)->get($this->client->url($integration, $path));
        } catch (Throwable $exception) {
            return ['connected' => false, 'message' => 'Não foi possível alcançar o destino: '.$exception->getMessage()];
        }

        if ($response->successful()) {
            return ['connected' => true, 'message' => 'Conexão testada com sucesso.'];
        }

        return [
            'connected' => false,
            'message' => 'O destino respondeu com o status '.$response->status().'.',
        ];
    }
}
