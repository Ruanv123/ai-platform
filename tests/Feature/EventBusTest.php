<?php

use App\Flows\FlowEventBus;
use App\Jobs\DeliverFlowEventWebhook;
use App\Jobs\DispatchFlowEventWebhooks;
use App\Models\FlowEvent;
use App\Models\FlowEventDelivery;
use App\Models\FlowEventWebhook;
use App\Models\FlowExecution;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('declares events per company and queues an emission for its subscribers', function () {
    Queue::fake();
    $user = User::factory()->withTeam()->create();
    $event = FlowEvent::factory()->for($user->company())->create(['name' => 'lead.qualificado']);
    $flow = $user->company()->flows()->create([
        'name' => 'Event flow',
        'status' => 'published',
        'draft_dsl' => ['version' => 1, 'nodes' => [], 'edges' => []],
        'trigger_type' => 'manual',
        'collision_policy' => 'ignore',
        'allow_unknown_contact' => false,
    ]);
    $version = $flow->versions()->create([
        'team_id' => $user->company()->getKey(),
        'number' => 1,
        'dsl' => $flow->draft_dsl,
        'category' => 'reaction',
        'published_at' => now(),
    ]);
    $execution = FlowExecution::factory()->for($version)->create(['team_id' => $user->company()->getKey()]);

    app(FlowEventBus::class)->emit($execution, $event->getKey(), ['score' => 10]);

    $this->assertDatabaseHas('flow_event_emissions', [
        'flow_event_id' => $event->getKey(),
        'event_name' => 'lead.qualificado',
    ]);
    Queue::assertPushed(DispatchFlowEventWebhooks::class);
    expect($execution->fresh()->emitted_events)->toContain('lead.qualificado');
});

it('signs a webhook delivery and records success', function () {
    Http::preventStrayRequests();
    Http::fake(['https://client.example.test/*' => Http::response(['received' => true])]);
    $user = User::factory()->withTeam()->create();
    $event = FlowEvent::factory()->for($user->company())->create(['name' => 'appointment.created']);
    $webhook = FlowEventWebhook::factory()->for($event)->for($user->company())->create([
        'url' => 'https://client.example.test/events',
        'secret' => 'delivery-secret',
    ]);
    $execution = FlowExecution::factory()->create(['team_id' => $user->company()->getKey()]);
    $emission = $event->emissions()->create([
        'team_id' => $user->company()->getKey(),
        'flow_id' => $execution->flowVersion->flow_id,
        'flow_version_id' => $execution->flow_version_id,
        'flow_execution_id' => $execution->getKey(),
        'event_name' => $event->name,
        'payload' => ['appointment_id' => 'a-1'],
        'occurred_at' => now(),
    ]);
    $payload = [
        'id' => $emission->getKey(),
        'event' => 'appointment.created',
        'payload' => ['appointment_id' => 'a-1'],
        'occurred_at' => $emission->occurred_at->toIso8601String(),
        'flow_id' => $emission->flow_id,
        'flow_version_id' => $emission->flow_version_id,
    ];
    $delivery = FlowEventDelivery::create([
        'team_id' => $user->company()->getKey(),
        'flow_event_id' => $event->getKey(),
        'flow_event_webhook_id' => $webhook->getKey(),
        'flow_event_emission_id' => $emission->getKey(),
        'payload' => $payload,
    ]);

    (new DeliverFlowEventWebhook($delivery->getKey()))->handle();

    expect($delivery->fresh()->status)->toBe('delivered');
    Http::assertSent(fn ($request): bool => $request->header('X-Event-Signature')[0] === 'sha256='.hash_hmac(
        'sha256',
        json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'delivery-secret',
    ));
});

it('keeps a permanent webhook failure visible for manual retry', function () {
    Http::preventStrayRequests();
    Http::fake(['https://client.example.test/*' => Http::response(['no' => true], 410)]);
    $user = User::factory()->withTeam()->create();
    $event = FlowEvent::factory()->for($user->company())->create();
    $webhook = FlowEventWebhook::factory()->for($event)->for($user->company())->create(['url' => 'https://client.example.test/events']);
    $execution = FlowExecution::factory()->create(['team_id' => $user->company()->getKey()]);
    $emission = $event->emissions()->create([
        'team_id' => $user->company()->getKey(),
        'flow_id' => $execution->flowVersion->flow_id,
        'flow_version_id' => $execution->flow_version_id,
        'flow_execution_id' => $execution->getKey(),
        'event_name' => $event->name,
        'payload' => [],
        'occurred_at' => now(),
    ]);
    $delivery = FlowEventDelivery::create([
        'team_id' => $user->company()->getKey(),
        'flow_event_id' => $event->getKey(),
        'flow_event_webhook_id' => $webhook->getKey(),
        'flow_event_emission_id' => $emission->getKey(),
        'payload' => ['id' => $emission->getKey(), 'event' => $event->name, 'payload' => []],
    ]);

    (new DeliverFlowEventWebhook($delivery->getKey()))->handle();

    expect($delivery->fresh()->status)->toBe('failed')
        ->and($delivery->fresh()->response_status)->toBe(410);
});
