<?php

use App\Ai\Agents\WhatsAppAgent;
use App\Enums\AgentObjective;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\User;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

it('uses the current company voice for an active Agent without publishing a new version', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $agent = Agent::factory()->for($team)->create(['is_active' => true]);
    AgentVersion::factory()->for($agent)->create(['team_id' => $team->id]);
    $publishedVersionId = $agent->fresh()->publishedVersion()->id;

    $this->actingAs($user)
        ->patch(route('company.update'), [
            'brand_voice' => 'Acolhedora e objetiva.',
            'brand_voice_examples' => ['Vamos ajudar você.', 'Conte com a nossa equipe.'],
        ])
        ->assertRedirect(route('company.edit'));

    $channelAgent = new WhatsAppAgent(
        $agent->fresh(),
        collect(),
        app(AgentInstructionTemplate::class),
        app(KnowledgeBaseSearch::class),
    );
    $instructions = (string) $channelAgent->instructions();

    expect($instructions)
        ->toContain('Acolhedora e objetiva.')
        ->toContain('Vamos ajudar você.')
        ->and($agent->fresh()->publishedVersion()->id)->toBe($publishedVersionId)
        ->and($agent->versions()->count())->toBe(1);
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
