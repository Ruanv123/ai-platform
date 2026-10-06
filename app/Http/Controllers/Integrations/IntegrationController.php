<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Integrations\GoogleCalendarClient;
use App\Models\Integration;
use App\Models\SchedulableCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class IntegrationController extends Controller
{
    /** @var list<string> */
    private const GOOGLE_SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.freebusy',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
    ];

    public function index(Request $request): Response
    {
        $team = $request->user()->company();
        $team->load('integrations');
        $configured = $team->integrations->keyBy('type');

        return Inertia::render('integrations/index', [
            'agendaCatalog' => [$this->agendaCatalogEntry()],
            'agenda' => optional($configured->get('agenda'), fn (Integration $integration): array => $this->serialize($integration)),
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function show(Request $request, string $type): Response
    {
        $team = $request->user()->company();
        $integration = $team->integrations()->where('type', $type)->first();
        $agendaCalendars = [];
        $googleCalendars = [];
        $agendaError = session('agenda_error');

        if ($type === 'agenda' && $integration !== null) {
            if ($integration->status === 'connected') {
                try {
                    $googleCalendars = (new GoogleCalendarClient($integration))->calendars();
                    $googleCalendarIds = collect($googleCalendars)->pluck('id')->filter()->all();
                    $integration->schedulableCalendars()
                        ->whereNotIn('google_calendar_id', $googleCalendarIds)
                        ->delete();
                } catch (Throwable $exception) {
                    $agendaError ??= $exception->getMessage();
                }
            }
            $agendaCalendars = $integration->schedulableCalendars()
                ->orderBy('display_name')
                ->get()
                ->map(fn (SchedulableCalendar $calendar): array => $this->serializeSchedulableCalendar($calendar))
                ->values()
                ->all();
        }

        return Inertia::render('integrations/show', [
            'integration' => $integration === null ? null : $this->serialize($integration),
            'googleCalendars' => $googleCalendars,
            'schedulableCalendars' => $agendaCalendars,
            'agendaError' => $agendaError,
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        return Socialite::driver('google')
            ->setScopes(self::GOOGLE_SCOPES)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $state = $request->input('state');
        $sessionState = $request->session()->get('state');
        if ($request->has('state') && (! is_string($state) || ! is_string($sessionState) || ! hash_equals($sessionState, $state))) {
            return to_route('integrations.show', 'agenda')->with('agenda_error', 'A autorização da Agenda expirou. Tente conectar novamente.');
        }
        if ($request->filled('error')) {
            return to_route('integrations.show', 'agenda')->with('agenda_error', 'A autorização da Agenda foi recusada no Google.');
        }

        try {
            $googleUser = Socialite::driver('google')
                ->setScopes(self::GOOGLE_SCOPES)
                ->user();
            $email = $googleUser->getEmail();
            if (! is_string($email) || $email === '' || ! is_string($googleUser->token ?? null)) {
                throw new RuntimeException('O Google não retornou uma conta válida.');
            }

            $team = $request->user()->company();
            $existing = $team->integrations()->where('type', 'agenda')->first();
            $integration = $existing ?? $team->integrations()->make(['type' => 'agenda']);
            $integration->fill([
                'name' => 'Google Agenda',
                'type' => 'agenda',
                'provider' => 'google',
                'credentials' => [
                    'access_token' => $googleUser->token,
                    'refresh_token' => is_string($googleUser->refreshToken ?? null) ? $googleUser->refreshToken : ($existing?->credential('refresh_token')),
                    'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600))->timestamp,
                    'email' => $email,
                ],
                'status' => 'connected',
                'status_message' => null,
                'last_tested_at' => now(),
            ]);
            $integration->save();

            return to_route('integrations.show', 'agenda')->with('agenda_success', 'Agenda conectada como '.$email.'.');
        } catch (Throwable $exception) {
            report($exception);

            return to_route('integrations.show', 'agenda')->with('agenda_error', 'Não foi possível conectar a Agenda. '.$exception->getMessage());
        }
    }

    public function destroy(Request $request, Integration $integration): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request, $integration);
        if ($integration->type === 'agenda') {
            try {
                $token = $integration->credential('access_token');
                if (is_string($token) && $token !== '') {
                    Http::connectTimeout(3)->timeout(5)->asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
                }
            } catch (Throwable $exception) {
                Log::warning('Google Agenda token revocation failed', ['integration_id' => $integration->getKey(), 'exception' => $exception]);
            }
        }

        $integration->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integração apagada.']);

        return to_route('integrations.index');
    }

    /** @return array<string, mixed> */
    private function agendaCatalogEntry(): array
    {
        return [
            'value' => 'google',
            'name' => 'Google Agenda',
            'description' => 'Conecte sua Agenda para que o Agente consulte horários e marque Compromissos.',
            'logo' => 'G',
            'capabilities' => ['Consultar horários', 'Marcar Compromissos', 'Cancelar Compromissos'],
            'planned' => false,
            'available' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(Integration $integration): array
    {
        return [
            'id' => $integration->getKey(),
            'name' => $integration->name,
            'type' => $integration->type,
            'provider' => $integration->provider,
            'accountEmail' => $integration->credential('email'),
            'status' => $integration->status,
            'statusLabel' => match ($integration->status) {
                'connected' => 'Conectada',
                'error' => 'Com erro',
                default => 'Nunca testada',
            },
            'statusMessage' => $integration->status_message,
            'lastTestedAt' => $integration->last_tested_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSchedulableCalendar(SchedulableCalendar $calendar): array
    {
        return [
            'id' => $calendar->id,
            'googleCalendarId' => $calendar->google_calendar_id,
            'displayName' => $calendar->display_name,
            'displayDescription' => $calendar->display_description,
            'durationMinutes' => $calendar->duration_minutes,
            'minimumNoticeMinutes' => $calendar->minimum_notice_minutes,
            'businessHours' => $calendar->business_hours,
        ];
    }

    private function authorizeManagement(Request $request, ?Integration $integration = null): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
        if ($integration !== null) {
            abort_unless($integration->team_id === $team->getKey(), 404);
        }
    }
}
