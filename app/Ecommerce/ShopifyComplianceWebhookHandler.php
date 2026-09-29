<?php

namespace App\Ecommerce;

use App\Enums\TeamRole;
use App\Mail\ShopifyCustomerDataExport;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class ShopifyComplianceWebhookHandler
{
    public function __construct(private ShopifyInstallationCleanup $installationCleanup) {}

    public function handle(string $topic, string $shopDomain, ?string $customerExternalId): void
    {
        match ($topic) {
            'customers/redact' => $this->redactCustomer($shopDomain, $customerExternalId),
            'customers/data_request' => $this->sendCustomerDataExport($shopDomain, $customerExternalId),
            'shop/redact' => $this->installationCleanup->redactShopByDomain($shopDomain),
            default => null,
        };
    }

    private function redactCustomer(string $shopDomain, ?string $customerExternalId): void
    {
        if ($customerExternalId === null) {
            return;
        }

        $shop = $this->shopByDomain($shopDomain);
        if ($shop === null) {
            return;
        }

        DB::transaction(function () use ($shop, $customerExternalId): void {
            $customer = StoreCustomer::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->where('external_id', $customerExternalId)
                ->first();

            $orders = StoreOrder::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->where(function (Builder $query) use ($customer, $customerExternalId): void {
                    if ($customer !== null) {
                        $query->where('store_customer_id', $customer->getKey())
                            ->orWhere('customer_external_id', $customerExternalId);

                        return;
                    }

                    $query->where('customer_external_id', $customerExternalId);
                });
            $orderIds = $orders->pluck('id');

            DB::table('store_order_items')->whereIn('store_order_id', $orderIds)->delete();
            $orders->delete();
            $customer?->delete();
        });
    }

    private function sendCustomerDataExport(string $shopDomain, ?string $customerExternalId): void
    {
        if ($customerExternalId === null) {
            return;
        }

        $shop = $this->shopByDomain($shopDomain);
        if ($shop === null) {
            return;
        }

        $team = Team::withTrashed()->find($shop->team_id);
        $owner = $team?->members()->wherePivot('role', TeamRole::Owner->value)->first();
        if ($owner === null || ! is_string($owner->email) || $owner->email === '') {
            return;
        }

        $customer = StoreCustomer::withoutGlobalScopes()
            ->where('shop_id', $shop->getKey())
            ->where('external_id', $customerExternalId)
            ->first();
        $orders = StoreOrder::withoutGlobalScopes()
            ->with(['items', 'purchaseAttribution.agent', 'purchaseAttribution.channel'])
            ->where('shop_id', $shop->getKey())
            ->where(function (Builder $query) use ($customer, $customerExternalId): void {
                if ($customer !== null) {
                    $query->where('store_customer_id', $customer->getKey())
                        ->orWhere('customer_external_id', $customerExternalId);

                    return;
                }

                $query->where('customer_external_id', $customerExternalId);
            })
            ->orderBy('ordered_at')
            ->orderBy('id')
            ->get();

        $export = [
            'shop_domain' => $shop->domain,
            'customer' => $customer === null ? null : [
                'external_id' => $customer->external_id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone_e164' => $customer->phone_e164,
                'total_spent' => $customer->total_spent,
                'currency_code' => $customer->currency_code,
                'contact_id' => $customer->contact_id,
            ],
            'orders' => $orders->map(fn (StoreOrder $order): array => [
                'external_id' => $order->external_id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'currency_code' => $order->currency_code,
                'financial_status' => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'items' => $order->items->map(fn (StoreOrderItem $item): array => [
                    'external_id' => $item->external_id,
                    'title' => $item->title,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
                'attribution' => $order->purchaseAttribution === null ? null : [
                    'attributed_at' => $order->purchaseAttribution->created_at?->toIso8601String(),
                    'contact_id' => $order->purchaseAttribution->contact_id,
                    'agent_name' => $order->purchaseAttribution->agent?->name,
                    'channel_type' => $order->purchaseAttribution->channel?->type?->value,
                ],
            ])->all(),
        ];

        $jsonContent = json_encode(
            $export,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        Mail::to($owner->email)->send(new ShopifyCustomerDataExport($shopDomain, $jsonContent));
    }

    private function shopByDomain(string $shopDomain): ?Shop
    {
        return Shop::withoutGlobalScope(TeamScope::class)
            ->withTrashed()
            ->where('domain', strtolower($shopDomain))
            ->first();
    }
}
