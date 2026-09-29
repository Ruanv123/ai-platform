<?php

namespace App\Http\Controllers\Integrations;

use App\Ecommerce\ShopifyInstallationBinder;
use App\Ecommerce\ShopifyInstallationFlow;
use App\Ecommerce\ShopifyReplacementConfirmationRequired;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;

final class ShopifyInstallationController extends Controller
{
    public function start(Request $request, string $handoffToken, ShopifyInstallationFlow $flow): RedirectResponse
    {
        $handoffHash = hash('sha256', $handoffToken);
        $pending = DB::transaction(function () use ($handoffHash): ?ShopifyPendingInstallation {
            $pending = ShopifyPendingInstallation::query()
                ->where('handoff_token_hash', $handoffHash)
                ->whereNull('handoff_started_at')
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            $pending?->forceFill(['handoff_started_at' => now()])->save();

            return $pending;
        });
        abort_if($pending === null, 410, 'O link de instalação expirou ou já foi usado.');

        $request->session()->put(ShopifyInstallationFlow::SESSION_KEY, [
            'id' => $pending->getKey(),
            'handoff_hash' => $handoffHash,
        ]);

        return $flow->redirectAfterAuthentication($request) ?? to_route('login');
    }

    public function resume(Request $request, ShopifyInstallationFlow $flow): RedirectResponse|InertiaResponse
    {
        $pending = $flow->pendingForRequest($request);
        abort_if($pending === null, 410, 'O link de instalação expirou ou já foi usado.');

        if (! $request->user()->teams()->exists()) {
            return to_route('onboarding.company');
        }

        return Inertia::render('shopify/install', [
            'shop' => [
                'domain' => $pending->domain,
                'name' => $pending->shop_name,
            ],
            'teams' => $this->eligibleTeams($request->user()),
        ]);
    }

    public function store(
        Request $request,
        ShopifyInstallationFlow $flow,
        ShopifyInstallationBinder $binder,
    ): RedirectResponse|InertiaResponse {
        $validated = $request->validate([
            'team_id' => ['required', 'integer'],
            'confirm_replacement' => ['sometimes', 'boolean'],
        ]);
        $pending = $flow->pendingForRequest($request);
        abort_if($pending === null, 410, 'O link de instalação expirou ou já foi usado.');

        $team = $this->eligibleTeam($request->user(), (int) $validated['team_id']);
        abort_if($team === null, 403);

        try {
            $binder->bind(
                (string) $pending->getKey(),
                (string) $pending->handoff_token_hash,
                $team,
                $request->user(),
                (bool) ($validated['confirm_replacement'] ?? false),
            );
        } catch (ShopifyReplacementConfirmationRequired $exception) {
            return Inertia::render('shopify/install-confirm', [
                'shop' => [
                    'domain' => $pending->domain,
                    'name' => $pending->shop_name,
                ],
                'team' => [
                    'id' => $team->getKey(),
                    'name' => $team->name,
                ],
                'existingShop' => [
                    'domain' => $exception->shop->domain,
                    'name' => $exception->shop->name,
                ],
            ]);
        } catch (RuntimeException $exception) {
            return to_route('shopify.install.resume')
                ->withErrors(['team_id' => $exception->getMessage()]);
        }

        $request->session()->forget(ShopifyInstallationFlow::SESSION_KEY);
        $request->user()->switchTeam($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Loja Shopify conectada.']);

        return to_route('integrations.show', [
            'type' => 'ecommerce',
            'provider' => 'shopify',
        ]);
    }

    private function eligibleTeams(User $user): array
    {
        return $user->teams()
            ->wherePivotIn('role', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->with(['shops' => fn ($query) => $query->withoutGlobalScope(TeamScope::class)->withTrashed()])
            ->orderBy('teams.name')
            ->get()
            ->map(fn (Team $team): array => [
                'id' => $team->getKey(),
                'name' => $team->name,
                'role' => $team->pivot->role,
                'shop' => $this->existingShopData($team->shops->first()),
            ])
            ->all();
    }

    private function eligibleTeam(User $user, int $teamId): ?Team
    {
        return $user->teams()
            ->wherePivotIn('role', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->where('teams.id', $teamId)
            ->first();
    }

    private function existingShopData(?Shop $shop): ?array
    {
        if ($shop === null) {
            return null;
        }

        return [
            'domain' => $shop->domain,
            'name' => $shop->name,
        ];
    }
}
