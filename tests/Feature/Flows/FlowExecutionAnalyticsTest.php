<?php

use App\Models\Flow;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders flow analytics when the graph contains edges', function () {
    $user = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($user->company())->create([
        'draft_dsl' => [
            'version' => 1,
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'config' => []],
                ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá']],
            ],
            'edges' => [
                ['source' => 'trigger', 'target' => 'message'],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('flows.show', $flow));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('flows/show')
        ->where('observability.period', '7d')
        ->where('observability.funnel.nodes.0.nodeId', 'trigger')
        ->where('observability.funnel.nodes.1.nodeId', 'message'),
    );
});
