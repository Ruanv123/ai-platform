<?php

namespace App\Jobs;

use App\Crm\CrmWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCrmWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 60;

    public function __construct(
        public string $integrationId,
        public int $teamId,
        public string $resource,
        public string $externalId,
        public bool $deleted,
    ) {
        $this->onQueue('crm-webhooks');
    }

    public function handle(CrmWebhookProcessor $processor): void
    {
        $processor->process($this->integrationId, $this->teamId, $this->resource, $this->externalId, $this->deleted);
    }

    public function uniqueId(): string
    {
        return implode(':', [$this->integrationId, $this->resource, $this->externalId]);
    }
}
