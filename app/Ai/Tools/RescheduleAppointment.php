<?php

namespace App\Ai\Tools;

use App\Models\Contact;
use App\Scheduling\SchedulingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class RescheduleAppointment implements Tool
{
    public function __construct(
        private ?Contact $contact,
        private Collection $calendars,
        private SchedulingService $scheduling,
        private bool $simulate = false,
    ) {}

    public function name(): string
    {
        return 'remarcar_compromisso';
    }

    public function description(): Stringable|string
    {
        return 'Remarque o Compromisso do próprio Contato, mantendo o mesmo evento no Google. Liste os compromissos primeiro e use somente o ID retornado. O Contato deve confirmar a nova data e hora. Não cancele o evento anterior nem crie outro para remarcar.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            if ($this->contact === null) {
                return json_encode(['ok' => false, 'simulado' => $this->simulate, 'motivo' => 'Não há Compromisso de um Contato para remarcar nesta conversa.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }

            $startInput = $request->string('start')->toString();
            if (blank($startInput)) {
                throw new \RuntimeException('Informe a nova data e hora confirmadas pelo Contato.');
            }

            $result = $this->scheduling->reschedule(
                $this->contact,
                $this->calendars,
                $request->string('appointment_id')->toString(),
                CarbonImmutable::parse($startInput, $this->contact->team->timezone ?: 'America/Sao_Paulo'),
                $this->simulate,
            );
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'motivo' => $exception->getMessage()];
        }

        return json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'appointment_id' => $schema->string()->description('ID do Compromisso retornado por listar_compromissos.')->required(),
            'start' => $schema->string()->description('Nova data e hora confirmadas, em ISO 8601 com fuso da Empresa.')->required(),
        ];
    }
}
