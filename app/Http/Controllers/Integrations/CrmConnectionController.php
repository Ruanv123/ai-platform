<?php

namespace App\Http\Controllers\Integrations;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CrmConnectionController extends Controller
{
    private const STATE_KEY = 'crm_rd_oauth.pending';

    private const STATE_LIFETIME_SECONDS = 600;

    public function connect(Request $request, CrmProviderAdapter $adapter): RedirectResponse
    {
        $this->authorizeManagement($request);
        $state = Str::random(64);
        $team = $request->user()->company();
        $request->session()->put(self::STATE_KEY.'.'.$state, [
            'team_id' => $team->getKey(),
            'expires_at' => now()->addSeconds(self::STATE_LIFETIME_SECONDS)->timestamp,
        ]);

        try {
            return redirect()->away($adapter->authorizationUrl($state));
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget(self::STATE_KEY.'.'.$state);

            return to_route('integrations.show', 'crm')->with('crm_error', 'Não foi possível iniciar a conexão com o RD Station CRM. Verifique a configuração do aplicativo.');
        }
    }

    public function callback(Request $request, CrmProviderAdapter $adapter): RedirectResponse
    {
        $this->authorizeManagement($request);
        $state = $request->query('state');
        if (! is_string($state) || $state === '') {
            return $this->failed('A autorização expirou. Tente conectar novamente.');
        }

        $pending = $request->session()->pull(self::STATE_KEY.'.'.$state);
        if (! is_array($pending) || ! is_numeric($pending['expires_at'] ?? null) || (int) $pending['expires_at'] < now()->timestamp) {
            return $this->failed('A autorização expirou. Tente conectar novamente.');
        }

        $team = $request->user()->company();
        if ($team === null || (int) ($pending['team_id'] ?? 0) !== $team->getKey()) {
            return $this->failed('A conexão precisa voltar à mesma Empresa que iniciou a autorização.');
        }

        if ($request->filled('error')) {
            return $this->failed('A autorização do RD Station CRM foi recusada.');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return $this->failed('O RD Station CRM não retornou o código de autorização. Tente novamente.');
        }

        try {
            $tokens = $adapter->exchangeAuthorizationCode($code);
            $integration = new Integration([
                'team_id' => $team->getKey(),
                'name' => 'RD Station CRM',
                'type' => 'crm',
                'provider' => $adapter->provider(),
                'credentials' => $tokens,
                'status' => 'connected',
            ]);
            $metadata = $adapter->loadMetadata($integration);

            DB::transaction(function () use ($team, $adapter, $tokens, $metadata): void {
                $integration = Integration::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('team_id', $team->getKey())
                    ->where('type', 'crm')
                    ->lockForUpdate()
                    ->first();
                $integration ??= new Integration;
                if ($integration->trashed()) {
                    $integration->restore();
                }
                $integration->forceFill([
                    'team_id' => $team->getKey(),
                    'name' => 'RD Station CRM',
                    'type' => 'crm',
                    'provider' => $adapter->provider(),
                    'credentials' => $tokens,
                    'crm_metadata' => $metadata,
                    'crm_metadata_loaded_at' => now(),
                    'status' => 'connected',
                    'status_message' => null,
                    'last_tested_at' => now(),
                ])->save();
            });

            return to_route('integrations.show', 'crm')->with('crm_success', 'RD Station CRM conectado.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed($exception->getMessage());
        }
    }

    private function authorizeManagement(Request $request): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageIntegrations), 403);
    }

    private function failed(string $message): RedirectResponse
    {
        return to_route('integrations.show', 'crm')->with('crm_error', $message);
    }
}
