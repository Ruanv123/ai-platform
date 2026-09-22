<?php

use App\Enums\ChannelType;
use App\Enums\FlowTriggerType;
use App\Models\Channel;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('paginates and filters the company flow listing', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    Flow::factory()->for($user->company())->count(6)->create(['channel_id' => $channel->getKey()]);

    $response = $this->actingAs($user)->get(route('flows.index', [
        'per_page' => 5,
        'channel' => $channel->getKey(),
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('flows/index')
        ->has('flows.data', 5)
        ->where('flows.currentPage', 1)
        ->where('flows.lastPage', 2)
        ->where('flows.perPage', 5)
        ->where('flows.total', 6)
        ->where('filters.channel', $channel->getKey())
        ->where('permissions.canManageFlows', true),
    );
});

it('searches the company flow listing by name', function () {
    $user = User::factory()->withTeam()->create();
    Flow::factory()->for($user->company())->create(['name' => 'Agendamento de consulta']);
    Flow::factory()->for($user->company())->create(['name' => 'Confirmação de retorno']);

    $response = $this->actingAs($user)->get(route('flows.index', ['search' => 'agendamento']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('flows/index')
        ->has('flows.data', 1)
        ->where('flows.data.0.name', 'Agendamento de consulta')
        ->where('filters.search', 'agendamento'),
    );
});

it('lets a member view a flow but forbids publishing it', function () {
    $owner = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($owner->company())->create();
    $member = User::factory()->create();
    $flow->team->members()->attach($member, ['role' => 'member']);
    $member->switchTeam($flow->team);

    $this->actingAs($member)->get(route('flows.show', $flow))->assertOk();

    $this->actingAs($member)->post(route('flows.publish', $flow))->assertForbidden();
});

it('clones a curated template as an independent draft', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('flows.templates.clone', 'agendamento'), ['channel_id' => $channel->getKey()]);

    $response->assertCreated()->assertJsonPath('status', 'draft');
    $flow = Flow::withoutGlobalScopes()->findOrFail($response->json('id'));

    expect($flow->draft_dsl['nodes'])->toHaveCount(4)
        ->and($flow->draft_dsl['nodes'][0]['type'])->toBe('trigger');
});

it('warns before publishing a webhook that starts with a free-form message', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $flow = Flow::factory()->for($user->company())->create([
        'channel_id' => $channel->getKey(),
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'appointment.created',
        'draft_dsl' => [
            'version' => 1,
            'nodes' => [
                ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá']],
            ],
            'edges' => [],
        ],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('flows.publish', $flow));

    $response->assertOk();
    expect($response->json('warnings.0'))->toContain('Template');
});

it('rejects an invalid AI proposal without changing the draft', function () {
    $user = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($user->company())->create();
    $originalDraft = $flow->draft_dsl;

    $this->actingAs($user)
        ->postJson(route('flows.ai.apply', $flow), [
            'dsl' => [
                'version' => 1,
                'nodes' => [['id' => 'unknown', 'type' => 'not_in_catalog', 'config' => []]],
                'edges' => [],
            ],
        ])
        ->assertUnprocessable();

    expect($flow->fresh()->draft_dsl)->toBe($originalDraft);
});

it('deletes a flow without execution history', function () {
    $user = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($user->company())->create();

    $this->actingAs($user)
        ->delete(route('flows.destroy', $flow))
        ->assertRedirect(route('flows.index'));

    $this->assertModelMissing($flow);
});

it('prevents deleting a flow that has execution history', function () {
    $user = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($user->company())->create();
    $version = $flow->versions()->create([
        'team_id' => $user->company()->getKey(),
        'number' => 1,
        'dsl' => $flow->draft_dsl,
        'category' => 'reaction',
        'published_at' => now(),
    ]);
    $execution = FlowExecution::factory()->create([
        'flow_version_id' => $version->getKey(),
    ]);

    $this->actingAs($user)
        ->delete(route('flows.destroy', $flow))
        ->assertSessionHasErrors('flow');

    $this->assertModelExists($flow);
    $this->assertModelExists($execution);
});

it('forbids a member from deleting a flow', function () {
    $owner = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($owner->company())->create();
    $member = User::factory()->create();
    $flow->team->members()->attach($member, ['role' => 'member']);
    $member->switchTeam($flow->team);

    $this->actingAs($member)
        ->delete(route('flows.destroy', $flow))
        ->assertForbidden();

    $this->assertModelExists($flow);
});

it('runs the draft in the playground without creating a metric execution', function () {
    $user = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($user->company())->create([
        'draft_dsl' => [
            'version' => 1,
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'config' => []],
                ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá']],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'message']],
        ],
    ]);

    $response = $this->actingAs($user)->postJson(route('flows.playground', $flow), ['message' => 'Oi']);

    $response->assertOk()
        ->assertJsonPath('isPlayground', true)
        ->assertJsonPath('executionId', null)
        ->assertJsonPath('outputs.0', 'Olá');
    $this->assertDatabaseCount('flow_executions', 0);
});
