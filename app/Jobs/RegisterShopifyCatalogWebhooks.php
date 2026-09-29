<?php

namespace App\Jobs;

use App\Ecommerce\EcommerceAdapter;
use App\Enums\EcommerceProvider;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class RegisterShopifyCatalogWebhooks implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public array $backoff = [10, 30, 60, 120];

    public int $uniqueFor = 3600;

    private const TOPICS = [
        'BULK_OPERATIONS_FINISH',
        'PRODUCTS_CREATE',
        'PRODUCTS_UPDATE',
        'PRODUCTS_DELETE',
        'INVENTORY_LEVELS_UPDATE',
        'COLLECTIONS_CREATE',
        'COLLECTIONS_UPDATE',
        'COLLECTIONS_DELETE',
        'CUSTOMERS_CREATE',
        'CUSTOMERS_UPDATE',
        'CUSTOMERS_DELETE',
        'ORDERS_CREATE',
        'ORDERS_UPDATED',
        'ORDERS_DELETE',
    ];

    private const SUBSCRIPTIONS_QUERY = <<<'GRAPHQL'
        query ShopifyCatalogWebhookSubscriptions($topics: [WebhookSubscriptionTopic!], $uri: String!) {
          webhookSubscriptions(first: 100, topics: $topics, uri: $uri) {
            nodes { topic uri }
          }
        }
        GRAPHQL;

    private const CREATE_SUBSCRIPTION_MUTATION = <<<'GRAPHQL'
        mutation CreateShopifyCatalogWebhook($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
          webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
            webhookSubscription { id topic uri }
            userErrors { field message }
          }
        }
        GRAPHQL;

    public function __construct(public string $shopId) {}

    public function uniqueId(): string
    {
        return $this->shopId;
    }

    public function handle(EcommerceAdapter $adapter): void
    {
        $shop = Shop::withoutGlobalScope(TeamScope::class)
            ->with('integration')
            ->whereKey($this->shopId)
            ->first();

        if ($shop === null || $shop->integration?->provider !== EcommerceProvider::Shopify->value || $shop->integration->status !== 'connected') {
            return;
        }

        $webhookUri = route('webhooks.shopify');
        $result = $adapter->queryShop($shop, self::SUBSCRIPTIONS_QUERY, [
            'topics' => self::TOPICS,
            'uri' => $webhookUri,
        ]);
        $subscriptions = data_get($result, 'webhookSubscriptions.nodes');

        if (! is_array($subscriptions)) {
            throw new RuntimeException('A Shopify não retornou as inscrições de webhook da Loja.');
        }

        $registeredTopics = collect($subscriptions)
            ->filter(fn (mixed $subscription): bool => is_array($subscription)
                && ($subscription['uri'] ?? null) === $webhookUri
                && is_string($subscription['topic'] ?? null))
            ->pluck('topic')
            ->all();

        foreach (self::TOPICS as $topic) {
            if (in_array($topic, $registeredTopics, true)) {
                continue;
            }

            $creation = $adapter->queryShop($shop, self::CREATE_SUBSCRIPTION_MUTATION, [
                'topic' => $topic,
                'webhookSubscription' => ['uri' => $webhookUri],
            ]);
            $createdSubscription = data_get($creation, 'webhookSubscriptionCreate.webhookSubscription');
            $userErrors = data_get($creation, 'webhookSubscriptionCreate.userErrors');

            if (! is_array($createdSubscription) || ! is_array($userErrors) || $userErrors !== []) {
                throw new RuntimeException('A Shopify não conseguiu registrar as inscrições de webhook do catálogo.');
            }
        }
    }
}
