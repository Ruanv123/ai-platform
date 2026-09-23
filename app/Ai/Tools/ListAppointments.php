<?php

namespace App\Ai\Tools;

use App\Models\Contact;
use App\Scheduling\SchedulingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ListAppointments implements Tool
{
    public function __construct(
        private ?Contact $contact,
        private SchedulingService $scheduling,
        private bool $simulate = false,
    ) {}

    public function description(): Stringable|string
    {
        return 'Liste os Compromissos futuros do próprio Contato, relendo a Agenda Google para respeitar horários movidos ou cancelados.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            if ($this->contact === null) {
                return $this->json(['ok' => true, 'simulado' => $this->simulate, 'compromissos' => []]);
            }

            return $this->json([
                'ok' => true,
                'simulado' => $this->simulate,
                'compromissos' => $this->scheduling->list($this->contact, $this->simulate),
            ]);
        } catch (Throwable $exception) {
            return $this->json(['ok' => false, 'motivo' => $exception->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function name(): string
    {
        return 'listar_compromissos';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
