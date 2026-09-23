<?php

namespace App\Integrations;

use App\Exceptions\SchedulingException;
use App\Models\Integration;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    private const TOKEN_API = 'https://oauth2.googleapis.com/token';

    public function __construct(private Integration $integration) {}

    /** @return list<array<string, mixed>> */
    public function calendars(): array
    {
        $calendars = [];
        $pageToken = null;

        do {
            $query = array_filter(['maxResults' => 250, 'pageToken' => $pageToken]);
            $response = $this->request()->get('/users/me/calendarList', $query);
            $data = $this->decode($response);
            foreach ((array) ($data['items'] ?? []) as $calendar) {
                if (is_array($calendar) && isset($calendar['id'])) {
                    $calendars[] = $calendar;
                }
            }
            $pageToken = is_string($data['nextPageToken'] ?? null) ? $data['nextPageToken'] : null;
        } while ($pageToken !== null);

        return $calendars;
    }

    /** @return array<string, array{busy: list<array{start: string, end: string}>}> */
    public function freeBusy(CarbonInterface $start, CarbonInterface $end, string $timezone, array $calendarIds): array
    {
        $response = $this->request()->post('/freeBusy', [
            'timeMin' => $start->utc()->toIso8601String(),
            'timeMax' => $end->utc()->toIso8601String(),
            'timeZone' => $timezone,
            'items' => array_map(fn (string $id): array => ['id' => $id], $calendarIds),
        ]);
        $data = $this->decode($response);

        $result = [];
        foreach ((array) ($data['calendars'] ?? []) as $calendarId => $calendar) {
            if (is_array($calendar) && ! empty($calendar['errors'])) {
                throw SchedulingException::unavailable('O Google não permitiu consultar a ocupação de um Calendário.');
            }

            $busyPeriods = is_array($calendar) ? ($calendar['busy'] ?? []) : [];
            $result[(string) $calendarId] = [
                'busy' => collect((array) $busyPeriods)
                    ->filter(fn (mixed $period): bool => is_array($period) && isset($period['start'], $period['end']))
                    ->map(fn (array $period): array => [
                        'start' => (string) $period['start'],
                        'end' => (string) $period['end'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $event */
    public function createEvent(string $calendarId, array $event): array
    {
        return $this->decode($this->request()->post('/calendars/'.rawurlencode($calendarId).'/events', $event));
    }

    /** @return array<string, mixed>|null */
    public function event(string $calendarId, string $eventId): ?array
    {
        $response = $this->request()->get('/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId));
        if (in_array($response->status(), [404, 410], true)) {
            return null;
        }

        return $this->decode($response);
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $response = $this->request()->delete('/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId));
        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $this->decode($response, allowEmpty: true);
    }

    private function request(): PendingRequest
    {
        $this->ensureAccessToken();

        return Http::baseUrl(self::CALENDAR_API)
            ->connectTimeout(3)
            ->timeout(15)
            ->acceptJson()
            ->withToken((string) $this->integration->credential('access_token'));
    }

    private function ensureAccessToken(): void
    {
        if ($this->integration->status !== 'connected') {
            throw SchedulingException::unavailable($this->integration->status_message ?: 'Reconecte a conta Google');
        }

        $expiresAt = (int) $this->integration->credential('expires_at', 0);
        if ($expiresAt > now()->timestamp + 60) {
            return;
        }

        $refreshToken = $this->integration->credential('refresh_token');
        if (! is_string($refreshToken) || $refreshToken === '') {
            $this->markUnavailable();
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(15)
            ->post(self::TOKEN_API, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if ($response->json('error') === 'invalid_grant') {
            $this->markUnavailable();
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw SchedulingException::unavailable('Não foi possível renovar o acesso à Agenda Google. Reconecte a conta Google.');
        }

        $credentials = $this->integration->credentials;
        $credentials['access_token'] = $response->json('access_token');
        $credentials['expires_at'] = now()->addSeconds((int) $response->json('expires_in', 3600))->timestamp;
        if (is_string($response->json('refresh_token'))) {
            $credentials['refresh_token'] = $response->json('refresh_token');
        }
        $this->integration->forceFill([
            'credentials' => $credentials,
            'status' => 'connected',
            'status_message' => null,
        ])->save();
    }

    /** @return array<string, mixed> */
    private function decode(Response $response, bool $allowEmpty = false): array
    {
        if ($allowEmpty && $response->successful() && $response->body() === '') {
            return [];
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? 'O Google recusou a operação na Agenda.';
            throw SchedulingException::unavailable((string) $message);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw SchedulingException::unavailable('O Google retornou uma resposta inválida.');
        }

        return $data;
    }

    private function markUnavailable(): never
    {
        $this->integration->forceFill([
            'status' => 'error',
            'status_message' => 'Reconecte a conta Google',
        ])->save();

        throw SchedulingException::unavailable('Reconecte a conta Google');
    }
}
