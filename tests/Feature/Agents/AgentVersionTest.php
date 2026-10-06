<?php

use App\Enums\AgentObjective;
use App\Models\Agent;
use App\Models\User;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

function agentPublishingPayload(Agent $agent, array $overrides = []): array
{
    return array_merge([
        'name' => $agent->name,
        'description' => $agent->description,
        'objective' => $agent->objective->value,
        'tone' => $agent->tone->value,
        'uses_emojis' => $agent->uses_emojis,
        'response_length' => $agent->response_length->value,
        'welcome_message' => $agent->welcome_message,
        'instructions' => $agent->instructions,
        'rules' => $agent->rules,
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
    ], $overrides);
}

it('keeps saved drafts out of the published version until publishing', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create([
        'objective' => AgentObjective::Support,
    ]);

    $this->actingAs($user)->post(
        route('agents.publish', $agent),
        agentPublishingPayload($agent, ['objective' => AgentObjective::Sales->value]),
    )->assertRedirect(route('agents.edit', $agent));

    $firstVersion = $agent->fresh()->publishedVersion();
    expect($firstVersion)->not->toBeNull()
        ->and($firstVersion->number)->toBe(1)
        ->and($firstVersion->objective)->toBe(AgentObjective::Sales);

    $this->actingAs($user)->patch(
        route('agents.update', $agent),
        agentPublishingPayload($agent->fresh(), ['objective' => AgentObjective::Support->value]),
    )->assertRedirect(route('agents.edit', $agent));

    expect($agent->fresh()->objective)->toBe(AgentObjective::Support)
        ->and($agent->fresh()->publishedVersion()->number)->toBe(1)
        ->and($agent->fresh()->publishedVersion()->objective)->toBe(AgentObjective::Sales);

    $this->actingAs($user)->post(
        route('agents.publish', $agent),
        agentPublishingPayload($agent->fresh()),
    )->assertRedirect(route('agents.edit', $agent));

    $latestVersion = $agent->fresh()->publishedVersion();
    expect($latestVersion->number)->toBe(2)
        ->and($latestVersion->objective)->toBe(AgentObjective::Support);
});
