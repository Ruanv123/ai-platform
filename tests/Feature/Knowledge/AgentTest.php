<?php

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function agentDetails(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lia',
        'objective' => AgentObjective::Support->value,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => true,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => ['Seja objetiva.', 'Confirme o contexto antes de responder.'],
        'rules' => ['Não invente informações.'],
        'transfer_triggers' => ['O cliente pedir um humano.'],
        'collection_ids' => [],
    ], $overrides);
}

it('redirects guests to login when they visit agents', function () {
    $response = $this->get(route('agents.index'));

    $response->assertRedirect(route('login'));
});

it('renders only the current company agents and collections', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $collection = Collection::factory()->for($team)->create(['name' => 'Suporte']);
    $agent = Agent::factory()->for($team)->create([
        'name' => 'Lia',
        'objective' => AgentObjective::Support,
    ]);
    $agent->collections()->attach($collection);
    Agent::factory()->create(['name' => 'Outro agente']);

    $response = $this->actingAs($user)->get(route('agents.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('agents/index')
        ->where('permissions.canManageAgents', true)
        ->has('agents', 1)
        ->where('agents.0.name', 'Lia')
        ->where('agents.0.objective', AgentObjective::Support->value)
        ->where('agents.0.objectiveLabel', 'Atendimento / Suporte')
        ->where('agents.0.isActive', true)
        ->where('collections.0.name', 'Suporte')
        ->where('objectives.0.value', AgentObjective::Support->value)
        ->where('tones.1.label', 'Amigável')
        ->where('responseLengths.0.value', AgentResponseLength::Short->value),
    );
});

it('allows an owner to create an agent with ordered lists and selected collections', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $collection = Collection::factory()->for($team)->create(['name' => 'Vendas']);

    $response = $this->actingAs($user)->post(route('agents.store'), agentDetails([
        'collection_ids' => [$collection->id],
        'team_id' => 999999,
    ]));

    $response->assertRedirect(route('agents.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Agente criado com sucesso.',
    ]);

    $agent = Agent::query()->where('name', 'Lia')->firstOrFail();

    expect($agent->team_id)->toBe($team->id)
        ->and($agent->instructions)->toBe(['Seja objetiva.', 'Confirme o contexto antes de responder.'])
        ->and($agent->rules)->toBe(['Não invente informações.'])
        ->and($agent->transfer_triggers)->toBe(['O cliente pedir um humano.']);
    expect($agent->collections()->pluck('collections.id')->all())->toBe([$collection->id]);
});

it('rejects an agent that references a collection from another company', function () {
    $user = User::factory()->withTeam()->create();
    $otherCollection = Collection::factory()->create(['name' => 'Privada']);

    $response = $this->actingAs($user)->post(route('agents.store'), agentDetails([
        'collection_ids' => [$otherCollection->id],
    ]));

    $response->assertSessionHasErrors([
        'collection_ids.0' => 'Selecione apenas Coleções da sua Empresa.',
    ]);
    $this->assertDatabaseCount('agents', 0);
});

it('returns clear validation messages for invalid agent data', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('agents.store'), []);

    $response->assertSessionHasErrors([
        'name' => 'Informe o nome da persona.',
        'objective' => 'Selecione o objetivo do Agente.',
        'tone' => 'Selecione o tom de voz.',
        'response_length' => 'Selecione o tamanho das respostas.',
        'welcome_message' => 'Informe a mensagem de boas-vindas.',
    ]);
    $this->assertDatabaseCount('agents', 0);
});

it('allows an admin to update every agent field and its collections', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $oldCollection = Collection::factory()->for($team)->create(['name' => 'Antiga']);
    $newCollection = Collection::factory()->for($team)->create(['name' => 'Nova']);
    $agent = Agent::factory()->for($team)->create()->load('collections');
    $agent->collections()->attach($oldCollection);

    $response = $this->actingAs($admin)->patch(
        route('agents.update', $agent),
        agentDetails([
            'name' => 'Bia',
            'objective' => AgentObjective::Sales->value,
            'tone' => AgentTone::Casual->value,
            'uses_emojis' => false,
            'response_length' => AgentResponseLength::Medium->value,
            'welcome_message' => 'Oi! Vamos encontrar uma solução.',
            'instructions' => ['Apresente as opções disponíveis.'],
            'rules' => ['Não pressione o cliente.'],
            'transfer_triggers' => ['O cliente solicitar orçamento personalizado.'],
            'collection_ids' => [$newCollection->id],
        ]),
    );

    $response->assertRedirect(route('agents.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Agente atualizado com sucesso.',
    ]);

    $agent->refresh();
    expect($agent->name)->toBe('Bia')
        ->and($agent->objective)->toBe(AgentObjective::Sales)
        ->and($agent->tone)->toBe(AgentTone::Casual)
        ->and($agent->uses_emojis)->toBeFalse()
        ->and($agent->response_length)->toBe(AgentResponseLength::Medium)
        ->and($agent->instructions)->toBe(['Apresente as opções disponíveis.']);
    expect($agent->collections()->pluck('collections.id')->all())->toBe([$newCollection->id]);
});

it('allows an owner to activate and deactivate an agent', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->inactive()->create();

    $response = $this->actingAs($user)->patch(route('agents.toggle', $agent));

    $response->assertRedirect(route('agents.index'));
    expect($agent->fresh()->is_active)->toBeTrue();
});

it('allows an owner to delete an agent', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->delete(route('agents.destroy', $agent));

    $response->assertRedirect(route('agents.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Agente excluído com sucesso.',
    ]);
    $this->assertModelMissing($agent);
});

it('allows a member to view agents but forbids agent mutations', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $agent = Agent::factory()->for($team)->create();

    $getResponse = $this->actingAs($member)->get(route('agents.index'));
    $postResponse = $this->actingAs($member)->post(route('agents.store'), agentDetails());
    $patchResponse = $this->actingAs($member)->patch(route('agents.update', $agent), agentDetails());
    $toggleResponse = $this->actingAs($member)->patch(route('agents.toggle', $agent));
    $deleteResponse = $this->actingAs($member)->delete(route('agents.destroy', $agent));

    $getResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageAgents', false)
        ->has('agents', 1),
    );
    $postResponse->assertForbidden();
    $patchResponse->assertForbidden();
    $toggleResponse->assertForbidden();
    $deleteResponse->assertForbidden();
    $this->assertModelExists($agent);
});

it('returns not found when a company accesses another company agent', function () {
    $user = User::factory()->withTeam()->create();
    $otherAgent = Agent::factory()->create(['name' => 'Isolado']);

    $updateResponse = $this->actingAs($user)->patch(
        route('agents.update', $otherAgent),
        agentDetails(['name' => 'Tentativa']),
    );
    $toggleResponse = $this->actingAs($user)->patch(route('agents.toggle', $otherAgent));
    $deleteResponse = $this->actingAs($user)->delete(route('agents.destroy', $otherAgent));

    $updateResponse->assertNotFound();
    $toggleResponse->assertNotFound();
    $deleteResponse->assertNotFound();
    expect($otherAgent->fresh()->name)->toBe('Isolado');
});
