<?php

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('executes an HTTP node with the saved integration, retries, and maps the response', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/*' => Http::sequence()
            ->push(['temporary' => true], 503)
            ->push(['customer' => ['id' => 'customer-1']], 200),
    ]);
    config()->set('flows.http_retry_times', 1);

    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsApp]);
    $contact = Contact::factory()->for($team)->create(['phone' => '5511999999999']);
    $integration = Integration::factory()->for($team)->create([
        'status' => 'connected',
        'credentials' => [
            'base_url' => 'https://api.example.test',
            'auth_type' => 'bearer',
            'token' => 'saved-secret',
        ],
    ]);
    $flow = Flow::factory()->for($team)->create([
        'channel_id' => $channel->getKey(),
        'trigger_type' => 'webhook',
        'trigger_event' => 'contact.created',
        'allow_unknown_contact' => false,
        'status' => 'published',
        'draft_dsl' => [
            'version' => 1,
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'config' => []],
                ['id' => 'request', 'type' => 'http_request', 'config' => [
                    'integration_id' => $integration->getKey(),
                    'method' => 'GET',
                    'url' => '/customers',
                    'response_map' => ['customer_id' => 'customer.id'],
                ]],
                ['id' => 'end', 'type' => 'end', 'config' => []],
            ],
            'edges' => [
                ['source' => 'trigger', 'target' => 'request'],
                ['source' => 'request', 'target' => 'end'],
            ],
        ],
    ]);
    $flow->versions()->create([
        'team_id' => $team->getKey(),
        'number' => 1,
        'dsl' => $flow->draft_dsl,
        'category' => 'reaction',
        'published_at' => now(),
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'phone' => $contact->phone,
    ])->assertOk();

    $execution = FlowExecution::withoutGlobalScopes()->latest('created_at')->firstOrFail();
    expect($execution->status->value)->toBe('completed')
        ->and($execution->variables['customer_id'])->toBe('customer-1')
        ->and($execution->steps()->where('node_id', 'request')->value('retry_count'))->toBe(1);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->header('Authorization')[0] === 'Bearer saved-secret');
    expect(json_encode($execution->steps()->where('node_id', 'request')->value('http_request')))->not->toContain('saved-secret');
});
