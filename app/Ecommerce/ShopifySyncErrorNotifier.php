<?php

namespace App\Ecommerce;

use App\Enums\TeamRole;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Notifications\Shopify\ShopSyncErrorNotification;
use Illuminate\Support\Facades\Notification;

final class ShopifySyncErrorNotifier
{
    public function notifyById(string $shopId): void
    {
        $shop = Shop::withoutGlobalScope(TeamScope::class)
            ->whereKey($shopId)
            ->where('sync_status', 'error')
            ->first();
        if ($shop === null) {
            return;
        }

        $team = $shop->team()->first();
        if ($team === null) {
            return;
        }

        $recipients = $team->members()
            ->wherePivotIn('role', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            (new ShopSyncErrorNotification(
                $shop->name,
                $shop->domain,
                $shop->sync_error ?? 'A sincronização encontrou um erro.',
                $shop->suggested_action ?? 'Abra as Integrações para verificar a Loja.',
            ))->afterCommit(),
        );
    }
}
