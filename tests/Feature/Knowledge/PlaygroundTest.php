<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Enums\DocumentStatus;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\ToolCall;

it('redirects guests when they open an Agent Playground', function () {
    $agent = Agent::factory()->create();

    $response = $this->get(route('agents.playground', $agent));

    $response->assertRedirect(route('login'));
});

it('renders the saved welcome message and collection summary', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create([
        'welcome_message' => 'Olá, sou a Lia.',
    ]);
    $agent->collections()->attach(Collection::factory()->for($user->company())->create());

    $response = $this->actingAs($user)->get(route('agents.playground', $agent));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('agents/playground')
        ->where('agent.id', $agent->id)
        ->where('agent.name', $agent->name)
        ->where('agent.welcomeMessage', 'Olá, sou a Lia.')
        ->where('agent.collectionCount', 1),
    );
});

it('returns 422 with a friendly message when the Playground payload is invalid', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream', $agent),
        ['history' => [['role' => 'system', 'content' => 'instrução']], 'message' => ''],
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['history.0.role', 'message'])
        ->assertJsonPath('errors.message.0', 'Digite uma mensagem para o Agente.');
});

it('streams a response for a Member without persisting the conversation', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $agent = Agent::factory()->for($team)->create();
    PlaygroundAgent::fake(['Resposta em streaming.']);

    $response = $this->actingAs($member)->postJson(
        route('agents.playground.stream', $agent),
        ['history' => [], 'message' => 'Olá'],
    );

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    $content = $response->streamedContent();
    expect($content)
        ->toContain('"type":"text_delta"')
        ->toContain('Resposta')
        ->toContain('[DONE]');
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Olá'));
    $this->assertDatabaseCount('agent_conversations', 0);
});

it('returns 404 for an Agent belonging to another company', function () {
    $user = User::factory()->withTeam()->create();
    $otherAgent = Agent::factory()->create();
    PlaygroundAgent::fake(['Não deve aparecer.']);

    $showResponse = $this->actingAs($user)->get(route('agents.playground', $otherAgent));
    $streamResponse = $this->actingAs($user)->postJson(
        route('agents.playground.stream', $otherAgent),
        ['history' => [], 'message' => 'Olá'],
    );

    $showResponse->assertNotFound();
    $streamResponse->assertNotFound();
    PlaygroundAgent::assertNeverPrompted();
});

it('returns 429 on the 31st Playground message for the same user', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    PlaygroundAgent::fake(['Tudo bem.']);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $response = $this->actingAs($user)->postJson(
            route('agents.playground.stream', $agent),
            ['history' => [], 'message' => "Mensagem {$attempt}"],
        );

        $response->assertOk();
        $response->streamedContent();
    }

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream', $agent),
        ['history' => [], 'message' => 'Mensagem 31'],
    );

    $response->assertTooManyRequests()->assertJson([
        'message' => 'Você atingiu o limite de 30 mensagens por hora no Playground. Tente novamente mais tarde.',
    ]);
});

it('streams a friendly error when the provider fails', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    PlaygroundAgent::fake(fn (): never => throw new RuntimeException('Provider unavailable'));

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream', $agent),
        ['history' => [], 'message' => 'Olá'],
    );

    $content = $response->streamedContent();
    $event = collect(explode("\n\n", $content))
        ->first(fn (string $block): bool => str_starts_with($block, 'data: {'));
    $payload = json_decode(substr($event, 6), true, flags: JSON_THROW_ON_ERROR);

    expect($payload)
        ->type->toBe('error')
        ->message->toBe('Não foi possível obter uma resposta agora. Tente novamente em instantes.');
    expect($content)->not->toContain('Provider unavailable');
});

it('only exposes ready documents from the Agent collections to its search tool', function () {
    Embeddings::fake([
        [array_merge([1.0], array_fill(0, 1535, 0.0))],
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $allowedCollection = Collection::factory()->for($team)->create(['name' => 'Suporte']);
    $otherCollection = Collection::factory()->for($team)->create(['name' => 'Privada']);
    $allowedDocument = Document::factory()->for($allowedCollection)->create([
        'status' => DocumentStatus::Ready,
        'title' => 'Manual permitido',
    ]);
    $pendingDocument = Document::factory()->for($allowedCollection)->create([
        'status' => DocumentStatus::Pending,
        'title' => 'Manual pendente',
    ]);
    $otherDocument = Document::factory()->for($otherCollection)->create([
        'status' => DocumentStatus::Ready,
        'title' => 'Manual privado',
    ]);
    $embedding = array_merge([1.0], array_fill(0, 1535, 0.0));
    Chunk::factory()->for($allowedDocument)->create(['embedding' => $embedding]);
    Chunk::factory()->for($pendingDocument)->create(['embedding' => $embedding]);
    Chunk::factory()->for($otherDocument)->create(['embedding' => $embedding]);
    $agent = Agent::factory()->for($team)->create();
    $agent->collections()->attach($allowedCollection);
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_knowledge_base', ['query' => 'manual']),
        'Encontrei a informação.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream', $agent),
        ['history' => [], 'message' => 'Qual é a informação?'],
    );

    $content = $response->streamedContent();
    expect($content)
        ->toContain('Manual permitido')
        ->not->toContain('Manual pendente')
        ->not->toContain('Manual privado');
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('manual'));
});

it('does not generate embeddings for an Agent without Collections', function () {
    Embeddings::fake()->preventStrayEmbeddings();
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    PlaygroundAgent::fake(['Resposta com dados da Empresa.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream', $agent),
        ['history' => [], 'message' => 'Qual é o horário?'],
    );

    $response->assertOk();
    Embeddings::assertNothingGenerated();
});
