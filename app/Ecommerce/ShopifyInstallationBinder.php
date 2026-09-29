<?php

namespace App\Ecommerce;

use App\Enums\TeamRole;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\StartShopifyCatalogImport;
use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ShopifyInstallationBinder
{
    public function __construct(
        private EcommerceAdapter $adapter,
        private ShopifyInstallationCleanup $cleanup,
    ) {}

    public function bind(
        string $pendingId,
        string $handoffHash,
        Team $team,
        User $user,
        bool $confirmReplacement,
    ): Shop {
        if ($confirmReplacement) {
            $membership = $user->teamMemberships()
                ->where('team_id', $team->getKey())
                ->first();
            abort_unless($membership !== null && in_array($membership->role, [TeamRole::Owner, TeamRole::Admin], true), 403);

            $pending = ShopifyPendingInstallation::query()
                ->whereKey($pendingId)
                ->first();
            abort_unless($pending !== null
                && $pending->isActive()
                && $pending->handoff_started_at !== null
                && is_string($pending->handoff_token_hash)
                && hash_equals($pending->handoff_token_hash, $handoffHash), 410);

            $otherShop = Shop::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->where('domain', $pending->domain)
                ->where('team_id', '!=', $team->getKey())
                ->first();
            if ($otherShop !== null) {
                throw new RuntimeException('Esta loja Shopify já está vinculada a outra Empresa.');
            }

            $shopToReplace = Shop::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->where('team_id', $team->getKey())
                ->first();
            if ($shopToReplace !== null && $shopToReplace->domain !== $pending->domain && ! $shopToReplace->trashed()) {
                $this->adapter->uninstallApp($shopToReplace);
                $this->cleanup->clearMirrorForReplacement($shopToReplace);
            }
        }

        return DB::transaction(function () use ($pendingId, $handoffHash, $team, $user, $confirmReplacement): Shop {
            $membership = $user->teamMemberships()
                ->where('team_id', $team->getKey())
                ->lockForUpdate()
                ->first();
            abort_unless($membership !== null && in_array($membership->role, [TeamRole::Owner, TeamRole::Admin], true), 403);

            $pending = ShopifyPendingInstallation::query()
                ->whereKey($pendingId)
                ->lockForUpdate()
                ->first();
            abort_unless($pending !== null
                && $pending->isActive()
                && $pending->handoff_started_at !== null
                && is_string($pending->handoff_token_hash)
                && hash_equals($pending->handoff_token_hash, $handoffHash), 410);

            $otherShop = Shop::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->where('domain', $pending->domain)
                ->where('team_id', '!=', $team->getKey())
                ->lockForUpdate()
                ->first();
            if ($otherShop !== null) {
                throw new RuntimeException('Esta loja Shopify já está vinculada a outra Empresa.');
            }

            $existingShop = Shop::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->where('team_id', $team->getKey())
                ->lockForUpdate()
                ->first();
            if ($existingShop !== null
                && $existingShop->domain !== $pending->domain
                && ! $confirmReplacement
            ) {
                throw new ShopifyReplacementConfirmationRequired($existingShop);
            }

            $integration = null;

            if ($existingShop !== null && $existingShop->domain === $pending->domain) {
                $integration = Integration::withoutGlobalScope(TeamScope::class)
                    ->withTrashed()
                    ->whereKey($existingShop->integration_id)
                    ->where('team_id', $team->getKey())
                    ->lockForUpdate()
                    ->first();

                abort_unless($integration !== null && $integration->provider === 'shopify', 409);

                $mustRestartSync = $existingShop->trashed()
                    || $existingShop->sync_status === 'error'
                    || $integration->status === 'error';
                $integration->forceFill([
                    'credentials' => $pending->credentials,
                    'status' => 'connected',
                    'status_message' => null,
                ])->save();

                if ($integration->trashed()) {
                    $integration->restore();
                }

                $existingShop->forceFill([
                    'name' => $pending->shop_name,
                    'currency_code' => $pending->currency_code,
                    'country_code' => $pending->country_code,
                    'timezone' => $pending->timezone,
                    'sync_status' => $mustRestartSync ? 'importing' : $existingShop->sync_status,
                    'sync_progress' => $mustRestartSync ? 0 : $existingShop->sync_progress,
                    'bulk_operation_id' => $mustRestartSync ? null : $existingShop->bulk_operation_id,
                    'sync_processed_count' => $mustRestartSync ? 0 : $existingShop->sync_processed_count,
                    'sync_total_count' => $mustRestartSync ? null : $existingShop->sync_total_count,
                    'sync_error' => null,
                    'suggested_action' => null,
                ])->save();
                if ($existingShop->trashed()) {
                    $existingShop->restore();
                }

                $pending->forceFill([
                    'credentials' => [],
                    'handoff_token_hash' => null,
                    'consumed_at' => now(),
                ])->save();
                $user->switchTeam($team);

                if ($mustRestartSync) {
                    DB::afterCommit(function () use ($existingShop): void {
                        RegisterShopifyCatalogWebhooks::dispatch($existingShop->getKey());
                        StartShopifyCatalogImport::dispatch($existingShop->getKey());
                    });
                }

                return $existingShop;
            }

            if ($existingShop !== null && ! $existingShop->trashed()) {
                $integration = $this->cleanup->clearMirrorForReplacement($existingShop);
                $existingShop->refresh();
            } elseif ($existingShop !== null) {
                $integration = Integration::withoutGlobalScope(TeamScope::class)
                    ->withTrashed()
                    ->whereKey($existingShop->integration_id)
                    ->where('team_id', $team->getKey())
                    ->lockForUpdate()
                    ->first();
            }

            $integration ??= Integration::withoutGlobalScope(TeamScope::class)
                ->withTrashed()
                ->where('team_id', $team->getKey())
                ->where('type', 'ecommerce')
                ->where('provider', 'shopify')
                ->orderByDesc('updated_at')
                ->lockForUpdate()
                ->first();
            $integration ??= $team->integrations()->make();

            if ($integration->trashed()) {
                $integration->restore();
            }

            $integration->forceFill([
                'team_id' => $team->getKey(),
                'name' => $pending->shop_name,
                'type' => 'ecommerce',
                'provider' => 'shopify',
                'credentials' => $pending->credentials,
                'status' => 'connected',
                'status_message' => null,
                'last_tested_at' => now(),
            ])->save();

            $shopAttributes = [
                'integration_id' => $integration->getKey(),
                'domain' => $pending->domain,
                'name' => $pending->shop_name,
                'currency_code' => $pending->currency_code,
                'country_code' => $pending->country_code,
                'timezone' => $pending->timezone,
                'sync_status' => 'importing',
                'sync_progress' => 0,
                'bulk_operation_id' => null,
                'sync_processed_count' => 0,
                'sync_total_count' => null,
                'sync_error' => null,
                'suggested_action' => null,
            ];
            if ($existingShop === null) {
                $shop = $team->shops()->create($shopAttributes);
            } else {
                $existingShop->forceFill($shopAttributes)->save();
                if ($existingShop->trashed()) {
                    $existingShop->restore();
                }
                $shop = $existingShop;
            }

            $pending->forceFill([
                'credentials' => [],
                'handoff_token_hash' => null,
                'consumed_at' => now(),
            ])->save();
            $user->switchTeam($team);

            DB::afterCommit(function () use ($shop): void {
                RegisterShopifyCatalogWebhooks::dispatch($shop->getKey());
                StartShopifyCatalogImport::dispatch($shop->getKey());
            });

            return $shop;
        });
    }
}
