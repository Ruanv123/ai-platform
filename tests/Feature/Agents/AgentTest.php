<?php

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Support\Str;
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
        'collection_ids' => [],
    ], $overrides);
}

it('redirects guests to login when they visit agents', function () {
    $response = $this->get(route('agents.index'));

    $response->assertRedirect(route('login'));
});

it('renders the edit editor with the current company agent', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create([
        'name' => 'Lia',
        'instructions' => ['Seja objetiva.'],
        'rules' => ['Não invente informações.'],
    ]);

    $response = $this->actingAs($user)->get(route('agents.edit', $agent));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('agents/edit')
        ->where('agent.id', $agent->id)
        ->where('agent.name', 'Lia')
        ->where('agent.instructions.0', 'Seja objetiva.')
        ->where('agent.rules.0', 'Não invente informações.'),
    );
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
    Channel::factory()->for($team)->create([
        'agent_id' => $agent->id,
        'type' => ChannelType::WhatsApp,
    ]);
    Channel::factory()->for($team)->create([
        'agent_id' => $agent->id,
        'type' => ChannelType::WebsiteChat,
    ]);
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
        ->where('agents.0.collectionCount', 1)
        ->where('agents.0.channelCount', 2)
        ->where('agents.0.channels.0.typeLabel', 'WhatsApp')
        ->where('agents.0.channels.1.typeLabel', 'Chat no site')
        ->missing('agents.0.transferTriggers')
        ->where('collections.0.name', 'Suporte')
        ->where('objectives.0.value', AgentObjective::Support->value)
        ->where('tones.1.label', 'Amigável')
        ->where('responseLengths.0.value', AgentResponseLength::Short->value),
    );
});

it('allows an owner to quick-create an agent with a name and description', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();

    $response = $this->actingAs($user)->post(route('agents.store'), [
        'name' => 'Lia',
        'description' => 'Consultora de vendas para o site.',
        'team_id' => 999999,
    ]);

    $agent = Agent::query()->where('name', 'Lia')->firstOrFail();

    $response->assertRedirect(route('agents.edit', $agent));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Agente criado com sucesso.',
    ]);

    expect(Str::isUuid($agent->id))->toBeTrue()
        ->and($agent->team_id)->toBe($team->id)
        ->and($agent->description)->toBe('Consultora de vendas para o site.')
        ->and($agent->objective)->toBe(AgentObjective::Support)
        ->and($agent->tone)->toBe(AgentTone::Friendly)
        ->and($agent->response_length)->toBe(AgentResponseLength::Short)
        ->and($agent->welcome_message)->not->toBe('')
        ->and($agent->is_active)->toBeTrue();
});

it('ignores removed transfer triggers and never returns them', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('agents.store'), [
        'name' => 'Lia',
        'transfer_triggers' => ['O cliente pedir um humano.'],
    ]);

    $agent = Agent::query()->where('name', 'Lia')->firstOrFail();

    $response->assertRedirect(route('agents.edit', $agent));

    expect($agent->getAttributes())->not->toHaveKey('transfer_triggers');

    $indexResponse = $this->actingAs($user)->get(route('agents.index'));

    $indexResponse->assertInertia(fn (Assert $page) => $page
        ->missing('agents.0.transferTriggers'),
    );
});

it('rejects an agent update that references a collection from another company', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    $otherCollection = Collection::factory()->create(['name' => 'Privada']);

    $response = $this->actingAs($user)->patch(route('agents.update', $agent), agentDetails([
        'collection_ids' => [$otherCollection->id],
    ]));

    $response->assertSessionHasErrors([
        'collection_ids.0' => 'Selecione apenas Coleções da sua Empresa.',
    ]);
    expect($agent->collections()->pluck('collections.id')->all())->toBe([]);
});

it('returns clear validation messages for invalid agent data', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->post(route('agents.store'), []);

    $response->assertSessionHasErrors([
        'name' => 'Informe o nome do Agente.',
    ]);
    $this->assertDatabaseCount('agents', 0);
});

it('returns clear validation messages when updating an agent with invalid data', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->patch(route('agents.update', $agent), []);

    $response->assertSessionHasErrors([
        'name' => 'Informe o nome da persona.',
        'objective' => 'Selecione o objetivo do Agente.',
        'tone' => 'Selecione o tom de voz.',
        'response_length' => 'Selecione o tamanho das respostas.',
        'welcome_message' => 'Informe a mensagem de boas-vindas.',
    ]);
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
            'collection_ids' => [$newCollection->id],
        ]),
    );

    $response->assertRedirect(route('agents.edit', $agent));
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
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->create([
            'agent_id' => $agent->id,
            'type' => ChannelType::WhatsApp,
        ]);

    $response = $this->actingAs($user)->delete(route('agents.destroy', $agent));

    $response->assertRedirect(route('agents.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Agente excluído com sucesso.',
    ]);
    $this->assertModelMissing($agent);
    $this->assertModelExists($channel);
    expect($channel->fresh()->agent_id)->toBeNull()
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Connected);
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

it('allows a member to open the editor and use the Playground read-only', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $agent = Agent::factory()->for($team)->create();

    $editResponse = $this->actingAs($member)->get(route('agents.edit', $agent));

    $editResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('agents/edit')
        ->where('permissions.canManageAgents', false)
        ->where('agent.id', $agent->id),
    );
});

it('returns not found when a company opens another company agent editor', function () {
    $user = User::factory()->withTeam()->create();
    $otherAgent = Agent::factory()->create(['name' => 'Isolado']);

    $response = $this->actingAs($user)->get(route('agents.edit', $otherAgent));

    $response->assertNotFound();
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
