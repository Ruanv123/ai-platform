<?php

namespace App\Jobs;

use App\Models\FlowEventDelivery;
use App\Models\FlowEventEmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchFlowEventWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $emissionId) {}

    public function handle(): void
    {
        $emission = FlowEventEmission::withoutGlobalScopes()
            ->with(['event.webhooks'])
            ->find($this->emissionId);
        if ($emission === null) {
            return;
        }

        foreach ($emission->event->webhooks->where('active', true) as $webhook) {
            $delivery = FlowEventDelivery::withoutGlobalScopes()->firstOrCreate(
                [
                    'flow_event_webhook_id' => $webhook->getKey(),
                    'flow_event_emission_id' => $emission->getKey(),
                ],
                [
                    'team_id' => $emission->team_id,
                    'flow_event_id' => $emission->flow_event_id,
                    'status' => 'pending',
                    'payload' => $this->payload($emission),
                ],
            );

            if ($delivery->status !== 'delivered') {
                DeliverFlowEventWebhook::dispatch($delivery->getKey());
            }
        }
    }

    /** @return array<string, mixed> */
    private function payload(FlowEventEmission $emission): array
    {
        return [
            'id' => $emission->getKey(),
            'event' => $emission->event_name,
            'payload' => $emission->payload ?? [],
            'occurred_at' => $emission->occurred_at->toIso8601String(),
            'flow_id' => $emission->flow_id,
            'flow_version_id' => $emission->flow_version_id,
        ];
    }
}
