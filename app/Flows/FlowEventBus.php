<?php

namespace App\Flows;

use App\Jobs\DispatchFlowEventWebhooks;
use App\Models\FlowEvent;
use App\Models\FlowEventEmission;
use App\Models\FlowExecution;
use Illuminate\Support\Carbon;
use RuntimeException;

final class FlowEventBus
{
    /** @param array<string, mixed> $payload */
    public function emit(FlowExecution $execution, string $eventId, array $payload = []): FlowEventEmission
    {
        $event = FlowEvent::withoutGlobalScopes()
            ->where('team_id', $execution->team_id)
            ->find($eventId);
        if ($event === null) {
            throw new RuntimeException('O Evento escolhido não existe nesta Empresa.');
        }

        $events = $execution->emitted_events;
        $events[] = $event->name;
        $execution->forceFill(['emitted_events' => array_values(array_unique($events))])->save();

        $emission = FlowEventEmission::create([
            'team_id' => $execution->team_id,
            'flow_event_id' => $event->getKey(),
            'flow_execution_id' => $execution->getKey(),
            'flow_id' => $execution->flowVersion->flow_id,
            'flow_version_id' => $execution->flow_version_id,
            'event_name' => $event->name,
            'payload' => $payload,
            'occurred_at' => Carbon::now(),
        ]);

        DispatchFlowEventWebhooks::dispatch($emission->getKey());

        return $emission;
    }
}
