<?php

namespace App\Jobs\Integrations;

use App\Ecommerce\ShopifyComplianceWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessShopifyComplianceWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $topic,
        public string $shopDomain,
        public ?string $customerExternalId,
    ) {}

    public function handle(ShopifyComplianceWebhookHandler $handler): void
    {
        $handler->handle($this->topic, $this->shopDomain, $this->customerExternalId);
    }
}
