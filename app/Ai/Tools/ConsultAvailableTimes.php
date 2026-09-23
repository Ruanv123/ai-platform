<?php

namespace App\Ai\Tools;

use App\Models\SchedulableCalendar;
use App\Models\Team;
use App\Scheduling\SchedulingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ConsultAvailableTimes implements Tool
{
    /** @param Collection<int, SchedulableCalendar> $calendars */
    public function __construct(
        private Team $team,
        private Collection $calendars,
        private SchedulingService $scheduling,
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulte os Horários disponíveis reais. Calendários permitidos: '.$this->calendarDescription().'. O Contato pode pedir um profissional específico ou qualquer um. Nunca invente horários e use o fuso da Empresa.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $calendarId = $request->string('calendar_id')->toString() ?: null;
            $slots = $this->scheduling->available(
                $this->team,
                $this->calendars,
                $calendarId,
                $request->string('start_date')->toString() ?: $request->string('start')->toString() ?: null,
                $request->string('end_date')->toString() ?: $request->string('end')->toString() ?: null,
                $request->integer('limit', 10),
            );

            return $this->json(['ok' => true, 'horarios' => $slots]);
        } catch (Throwable $exception) {
            return $this->json(['ok' => false, 'motivo' => $exception->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'calendar_id' => $schema->string()->nullable()->description('ID do Calendário agendável escolhido; omita ou use "qualquer" para o primeiro horário livre entre todos.'),
            'start_date' => $schema->string()->nullable()->description('Data inicial no formato AAAA-MM-DD.'),
            'end_date' => $schema->string()->nullable()->description('Data final no formato AAAA-MM-DD.'),
            'limit' => $schema->integer()->nullable()->description('Quantidade desejada, limitada a 10.'),
        ];
    }

    public function name(): string
    {
        return 'consultar_horarios';
    }

    private function calendarDescription(): string
    {
        return $this->calendars->map(fn (SchedulableCalendar $calendar): string => $calendar->display_name.($calendar->display_description ? ' ('.$calendar->display_description.')' : ''))->implode(', ');
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
