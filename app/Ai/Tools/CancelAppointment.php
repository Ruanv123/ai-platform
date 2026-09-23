<?php

namespace App\Ai\Tools;

use App\Models\Contact;
use App\Scheduling\SchedulingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class CancelAppointment implements Tool
{
    public function __construct(
        private ?Contact $contact,
        private SchedulingService $scheduling,
        private bool $simulate = false,
    ) {}

    public function description(): Stringable|string
    {
        return 'Cancele somente um Compromisso futuro que foi listado para o próprio Contato. No Playground a operação é simulada e não altera o Google.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $appointmentId = $request->string('appointment_id')->toString();
            if ($appointmentId === '') {
                throw new \RuntimeException('Informe qual Compromisso o Contato quer cancelar.');
            }
            if ($this->contact === null) {
                return $this->json($this->scheduling->cancel(
                    $this->fakeContact(),
                    $appointmentId,
                    true,
                ));
            }

            return $this->json($this->scheduling->cancel($this->contact, $appointmentId, $this->simulate));
        } catch (Throwable $exception) {
            return $this->json(['ok' => false, 'motivo' => $exception->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'appointment_id' => $schema->string()->description('ID do Compromisso devolvido por listar_compromissos.')->required(),
        ];
    }

    public function name(): string
    {
        return 'cancelar_compromisso';
    }

    private function fakeContact(): Contact
    {
        return new Contact(['team_id' => 0, 'phone' => 'playground']);
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
