<?php

namespace App\Ai\Tools;

use App\Models\Contact;
use App\Models\SchedulableCalendar;
use App\Scheduling\SchedulingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class BookAppointment implements Tool
{
    /** @param Collection<int, SchedulableCalendar> $calendars */
    public function __construct(
        private ?Contact $contact,
        private Collection $calendars,
        private SchedulingService $scheduling,
        private bool $simulate = false,
    ) {}

    public function description(): Stringable|string
    {
        return 'Marque um Compromisso somente depois de o Contato confirmar explicitamente dia, hora e profissional. Repita esses três dados na confirmação antes de usar a ferramenta. Calendários permitidos: '.$this->calendarDescription().'.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $calendar = $this->calendar($request->string('calendar_id')->toString());
            $startInput = $request->string('start')->toString();
            if (blank($startInput)) {
                throw new \RuntimeException('Informe a data e hora confirmadas pelo Contato.');
            }
            $start = CarbonImmutable::parse($startInput, $calendar->team->timezone ?: 'America/Sao_Paulo');
            if ($this->contact === null && ! $this->simulate) {
                throw new \RuntimeException('Não há um Contato nesta conversa.');
            }
            if ($this->contact === null) {
                return $this->json($this->scheduling->book(
                    $this->fakeContact($calendar),
                    $calendar,
                    $start,
                    $request->string('reason')->toString(),
                    true,
                ));
            }

            return $this->json($this->scheduling->book(
                $this->contact,
                $calendar,
                $start,
                $request->string('reason')->toString(),
                $this->simulate,
            ));
        } catch (Throwable $exception) {
            return $this->json(['ok' => false, 'motivo' => $exception->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'calendar_id' => $schema->string()->description('ID do Calendário agendável confirmado pelo Contato.')->required(),
            'start' => $schema->string()->description('Data e hora confirmadas pelo Contato, com fuso da Empresa.')->required(),
            'reason' => $schema->string()->description('Motivo do atendimento informado pelo Contato.')->required(),
        ];
    }

    public function name(): string
    {
        return 'marcar_compromisso';
    }

    private function calendar(string $id): SchedulableCalendar
    {
        $calendar = $this->calendars->first(fn (SchedulableCalendar $item): bool => $item->getKey() === $id || $item->google_calendar_id === $id);
        if ($calendar === null) {
            throw new \RuntimeException('Escolha um Calendário agendável permitido para este Agente.');
        }

        return $calendar;
    }

    private function calendarDescription(): string
    {
        return $this->calendars->map(fn (SchedulableCalendar $calendar): string => $calendar->display_name.($calendar->display_description ? ' ('.$calendar->display_description.')' : ''))->implode(', ');
    }

    private function fakeContact(SchedulableCalendar $calendar): Contact
    {
        $contact = new Contact([
            'team_id' => $calendar->team_id,
            'phone' => 'playground',
            'name' => 'Contato do Playground',
        ]);
        $contact->exists = true;
        $contact->setRelation('team', $calendar->team);

        return $contact;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
