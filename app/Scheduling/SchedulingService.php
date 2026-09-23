<?php

namespace App\Scheduling;

use App\Exceptions\SchedulingException;
use App\Integrations\GoogleCalendarClient;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class SchedulingService
{
    /**
     * @param  Collection<int, SchedulableCalendar>  $calendars
     * @return list<array<string, mixed>>
     */
    public function available(
        Team $team,
        Collection $calendars,
        ?string $calendarId,
        ?string $startInput,
        ?string $endInput,
        int $limit = 10,
    ): array {
        $calendars = $this->selectCalendars($calendars, $calendarId);
        if ($calendars->isEmpty()) {
            return [];
        }

        $timezone = $this->timezone($team);
        $rangeStart = $this->parseRangeStart($startInput, $timezone);
        $rangeEnd = $this->parseRangeEnd($endInput, $rangeStart, $timezone);
        if ($rangeEnd->lessThanOrEqualTo($rangeStart)) {
            throw new SchedulingException('O intervalo solicitado para a Agenda é inválido.');
        }

        $busyByCalendar = $this->busyByCalendar($calendars, $rangeStart, $rangeEnd, $timezone);
        $slots = [];
        foreach ($calendars as $calendar) {
            $slots = [...$slots, ...$this->slotsForCalendar(
                $team,
                $calendar,
                $rangeStart,
                $rangeEnd,
                $timezone,
                $busyByCalendar[$calendar->getKey()] ?? [],
            )];
        }

        usort($slots, fn (array $left, array $right): int => strcmp((string) $left['inicio'], (string) $right['inicio']));

        return array_slice($slots, 0, min(max(1, $limit), 10));
    }

    /** @return array<string, mixed> */
    public function book(
        Contact $contact,
        SchedulableCalendar $calendar,
        CarbonImmutable $startsAt,
        string $reason,
        bool $simulate = false,
    ): array {
        $duration = (int) $calendar->duration_minutes;
        $endsAt = $startsAt->addMinutes($duration);
        if ($simulate) {
            return [
                'ok' => true,
                'simulado' => true,
                'compromisso_id' => 'playground-'.Str::uuid7(),
                'inicio' => $startsAt->toIso8601String(),
                'fim' => $endsAt->toIso8601String(),
                'calendario' => $this->calendarLabel($calendar),
            ];
        }

        if ($calendar->team_id !== $contact->team_id) {
            throw new SchedulingException('O Calendário não pertence à Empresa deste Contato.');
        }

        $lock = Cache::lock('schedulable-calendar:'.$calendar->getKey(), 15);

        try {
            return $lock->block(5, function () use ($contact, $calendar, $startsAt, $endsAt, $reason): array {
                $this->list($contact);

                $hasFutureAppointment = Appointment::query()
                    ->where('contact_id', $contact->getKey())
                    ->where('schedulable_calendar_id', $calendar->getKey())
                    ->where('status', 'booked')
                    ->where('starts_at', '>=', now())
                    ->exists();
                if ($hasFutureAppointment) {
                    throw new SchedulingException('O Contato já tem um Compromisso futuro neste Calendário.');
                }

                $team = $contact->team;
                $timezone = $this->timezone($team);
                $this->assertSlotCanBeBooked($team, $calendar, $startsAt, $endsAt, $timezone);
                $client = new GoogleCalendarClient($calendar->integration);
                $busy = $client->freeBusy($startsAt, $endsAt, $timezone, [$calendar->google_calendar_id]);
                if (($busy[$calendar->google_calendar_id]['busy'] ?? []) !== []) {
                    $alternatives = $this->available(
                        $team,
                        new Collection([$calendar]),
                        $calendar->getKey(),
                        $startsAt->toDateString(),
                        $startsAt->addDays(3)->toDateString(),
                        3,
                    );
                    throw new SchedulingException('O horário escolhido acabou de ser ocupado. Alternativas: '.json_encode($alternatives, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }

                $event = $client->createEvent($calendar->google_calendar_id, [
                    'summary' => ($contact->name ?: $contact->phone).' (via WhatsApp)',
                    'description' => 'Telefone: '.$contact->phone."\nMotivo: ".trim($reason),
                    'start' => [
                        'dateTime' => $startsAt->setTimezone($timezone)->toIso8601String(),
                        'timeZone' => $timezone,
                    ],
                    'end' => [
                        'dateTime' => $endsAt->setTimezone($timezone)->toIso8601String(),
                        'timeZone' => $timezone,
                    ],
                    'extendedProperties' => [
                        'private' => ['contact_id' => (string) $contact->getKey()],
                    ],
                ]);
                $eventId = $event['id'] ?? null;
                if (! is_string($eventId) || $eventId === '') {
                    throw new SchedulingException('O Google não retornou o identificador do Compromisso.');
                }

                $appointment = $contact->appointments()->create([
                    'team_id' => $contact->team_id,
                    'schedulable_calendar_id' => $calendar->getKey(),
                    'google_event_id' => $eventId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'reason' => trim($reason),
                    'status' => 'booked',
                ]);

                return [
                    'ok' => true,
                    'simulado' => false,
                    'compromisso_id' => $appointment->getKey(),
                    'google_event_id' => $eventId,
                    'inicio' => $startsAt->toIso8601String(),
                    'fim' => $endsAt->toIso8601String(),
                    'calendario' => $this->calendarLabel($calendar),
                ];
            });
        } catch (SchedulingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SchedulingException('Não foi possível marcar o Compromisso agora. Tente novamente.', previous: $exception);
        } finally {
            optional($lock)->release();
        }
    }

    /** @return list<array<string, mixed>> */
    public function list(Contact $contact, bool $simulate = false): array
    {
        if ($simulate) {
            return [];
        }

        $appointments = Appointment::query()
            ->with(['schedulableCalendar.integration'])
            ->where('contact_id', $contact->getKey())
            ->where('status', 'booked')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        $result = [];
        foreach ($appointments as $appointment) {
            $calendar = $appointment->schedulableCalendar;
            if ($calendar === null) {
                $appointment->update(['status' => 'cancelled']);

                continue;
            }

            $event = (new GoogleCalendarClient($calendar->integration))->event(
                $calendar->google_calendar_id,
                $appointment->google_event_id,
            );
            if ($event === null || ($event['status'] ?? null) === 'cancelled') {
                $appointment->update(['status' => 'cancelled']);

                continue;
            }

            $start = $this->eventDate($event, 'start') ?? $appointment->starts_at;
            $end = $this->eventDate($event, 'end') ?? $appointment->ends_at;
            if (! $start->equalTo($appointment->starts_at) || ! $end->equalTo($appointment->ends_at)) {
                $appointment->update(['starts_at' => $start, 'ends_at' => $end]);
            }
            if ($start->isPast()) {
                continue;
            }

            $result[] = [
                'compromisso_id' => $appointment->getKey(),
                'google_event_id' => $appointment->google_event_id,
                'inicio' => $start->toIso8601String(),
                'fim' => $end->toIso8601String(),
                'motivo' => $appointment->reason,
                'calendario' => $this->calendarLabel($calendar),
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function cancel(Contact $contact, string $appointmentId, bool $simulate = false): array
    {
        if ($simulate) {
            return ['ok' => true, 'simulado' => true, 'compromisso_id' => $appointmentId];
        }

        $appointment = Appointment::query()
            ->with(['schedulableCalendar.integration'])
            ->whereKey($appointmentId)
            ->where('team_id', $contact->team_id)
            ->first();
        if ($appointment === null || $appointment->contact_id !== $contact->getKey()) {
            throw new SchedulingException('Esse Compromisso não pertence a este Contato.');
        }
        if ($appointment->status !== 'booked' || $appointment->starts_at->isPast()) {
            throw new SchedulingException('Só é possível cancelar Compromissos futuros deste Contato.');
        }

        $calendar = $appointment->schedulableCalendar;
        if ($calendar !== null) {
            $client = new GoogleCalendarClient($calendar->integration);
            $event = $client->event($calendar->google_calendar_id, $appointment->google_event_id);
            if ($event === null || ($event['status'] ?? null) === 'cancelled') {
                $appointment->update(['status' => 'cancelled']);

                return ['ok' => true, 'simulado' => false, 'compromisso_id' => $appointment->getKey(), 'cancelado' => true];
            }

            $start = $this->eventDate($event, 'start');
            $end = $this->eventDate($event, 'end');
            if ($start !== null) {
                $appointment->starts_at = $start;
            }
            if ($end !== null) {
                $appointment->ends_at = $end;
            }
            if ($appointment->starts_at->isPast()) {
                throw new SchedulingException('Só é possível cancelar Compromissos futuros deste Contato.');
            }
            $appointment->save();
            $client->deleteEvent($calendar->google_calendar_id, $appointment->google_event_id);
        }
        $appointment->update(['status' => 'cancelled']);

        return ['ok' => true, 'simulado' => false, 'compromisso_id' => $appointment->getKey(), 'cancelado' => true];
    }

    /** @param Collection<int, SchedulableCalendar> $calendars */
    private function selectCalendars(Collection $calendars, ?string $calendarId): Collection
    {
        if ($calendarId === null || in_array(strtolower($calendarId), ['any', 'anyone', 'qualquer', '*'], true)) {
            return $calendars;
        }

        $calendar = $calendars->first(fn (SchedulableCalendar $item): bool => $item->getKey() === $calendarId || $item->google_calendar_id === $calendarId);
        if ($calendar === null) {
            throw new SchedulingException('O Agente não pode usar este Calendário.');
        }

        return new Collection([$calendar]);
    }

    /** @return array<string, list<array{start: string, end: string}>> */
    private function busyByCalendar(Collection $calendars, CarbonImmutable $start, CarbonImmutable $end, string $timezone): array
    {
        $busy = [];
        foreach ($calendars->groupBy('integration_id') as $integrationCalendars) {
            $integration = $integrationCalendars->first()->integration;
            $response = (new GoogleCalendarClient($integration))->freeBusy(
                $start,
                $end,
                $timezone,
                $integrationCalendars->pluck('google_calendar_id')->all(),
            );
            foreach ($integrationCalendars as $calendar) {
                $busy[$calendar->getKey()] = $response[$calendar->google_calendar_id]['busy'] ?? [];
            }
        }

        return $busy;
    }

    /** @param list<array{start: string, end: string}> $busy */
    private function slotsForCalendar(
        Team $team,
        SchedulableCalendar $calendar,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        string $timezone,
        array $busy,
    ): array {
        $duration = max(1, (int) $calendar->duration_minutes);
        $noticeFrom = CarbonImmutable::now($timezone)->addMinutes(max(0, (int) $calendar->minimum_notice_minutes));
        $slots = [];
        for ($day = $rangeStart->setTimezone($timezone)->startOfDay(); $day->lessThan($rangeEnd->setTimezone($timezone)); $day = $day->addDay()) {
            $hours = $this->hoursFor($team, $calendar, $day);
            if ($hours === null) {
                continue;
            }
            [$open, $close] = $hours;
            $cursor = $day->setTimeFromTimeString($open);
            $closeAt = $day->setTimeFromTimeString($close);
            while ($cursor->addMinutes($duration)->lessThanOrEqualTo($closeAt)) {
                $endsAt = $cursor->addMinutes($duration);
                if ($cursor->greaterThanOrEqualTo($noticeFrom)
                    && $cursor->greaterThanOrEqualTo($rangeStart)
                    && $endsAt->lessThanOrEqualTo($rangeEnd)
                    && ! $this->overlapsBusy($cursor, $endsAt, $busy)
                ) {
                    $slots[] = [
                        'calendar_id' => $calendar->getKey(),
                        'calendario' => $this->calendarLabel($calendar),
                        'descricao' => $calendar->display_description,
                        'inicio' => $cursor->toIso8601String(),
                        'fim' => $endsAt->toIso8601String(),
                    ];
                }
                $cursor = $endsAt;
            }
        }

        return $slots;
    }

    private function assertSlotCanBeBooked(Team $team, SchedulableCalendar $calendar, CarbonImmutable $start, CarbonImmutable $end, string $timezone): void
    {
        $hours = $this->hoursFor($team, $calendar, $start->setTimezone($timezone));
        if ($hours === null) {
            throw new SchedulingException('O horário escolhido está fora da janela de atendimento ou não respeita a antecedência mínima.');
        }

        $noticeFrom = CarbonImmutable::now($timezone)->addMinutes(max(0, (int) $calendar->minimum_notice_minutes));
        $localStart = $start->setTimezone($timezone);
        $localEnd = $end->setTimezone($timezone);
        $openAt = $localStart->setTimeFromTimeString($hours[0]);
        if ($localStart->lessThan($openAt) || $localStart->lessThan($noticeFrom) || $localEnd->greaterThan($localStart->setTimeFromTimeString($hours[1]))) {
            throw new SchedulingException('O horário escolhido está fora da janela de atendimento ou não respeita a antecedência mínima.');
        }
        if ($localStart->second !== 0 || $openAt->diffInMinutes($localStart) % max(1, (int) $calendar->duration_minutes) !== 0) {
            throw new SchedulingException('O horário escolhido não corresponde a uma fatia disponível.');
        }
    }

    /** @param list<array{start: string, end: string}> $busy */
    private function overlapsBusy(CarbonImmutable $start, CarbonImmutable $end, array $busy): bool
    {
        foreach ($busy as $period) {
            try {
                if ($start->lessThan(CarbonImmutable::parse($period['end'])) && $end->greaterThan(CarbonImmutable::parse($period['start']))) {
                    return true;
                }
            } catch (Throwable) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: string, 1: string}|null */
    private function hoursFor(Team $team, SchedulableCalendar $calendar, CarbonImmutable $day): ?array
    {
        $hours = $calendar->business_hours ?? $team->business_hours;
        $dayHours = is_array($hours) ? ($hours[strtolower($day->englishDayOfWeek)] ?? null) : null;
        if (! is_array($dayHours) || ($dayHours['enabled'] ?? true) === false || ($dayHours['enabled'] ?? true) === '0') {
            return null;
        }
        $open = $dayHours['open'] ?? null;
        $close = $dayHours['close'] ?? null;

        return is_string($open) && is_string($close) && $open < $close ? [$open, $close] : null;
    }

    private function timezone(Team $team): string
    {
        return $team->timezone ?: 'America/Sao_Paulo';
    }

    private function parseRangeStart(?string $value, string $timezone): CarbonImmutable
    {
        return $value === null || trim($value) === ''
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::parse($value, $timezone)->startOfDay();
    }

    private function parseRangeEnd(?string $value, CarbonImmutable $start, string $timezone): CarbonImmutable
    {
        return $value === null || trim($value) === ''
            ? $start->addDays(7)->endOfDay()
            : CarbonImmutable::parse($value, $timezone)->endOfDay();
    }

    private function calendarLabel(SchedulableCalendar $calendar): string
    {
        return $calendar->display_name.($calendar->display_description ? ' — '.$calendar->display_description : '');
    }

    private function eventDate(array $event, string $part): ?CarbonImmutable
    {
        $dateTime = data_get($event, $part.'.dateTime') ?? data_get($event, $part.'.date');

        return is_string($dateTime) ? CarbonImmutable::parse($dateTime) : null;
    }
}
