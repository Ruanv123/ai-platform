<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
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

function playgroundDetails(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lia',
        'objective' => AgentObjective::Support->value,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => true,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => ['Seja objetiva.'],
        'rules' => ['Não invente informações.'],
        'collection_ids' => [],
        'history' => [],
        'message' => 'Olá',
    ], $overrides);
}

it('rejects guests when they send a Playground message', function () {
    $response = $this->postJson(
        route('agents.playground.stream'),
        playgroundDetails(),
    );

    $response->assertUnauthorized();
});

it('streams a draft Agent that does not exist in the database', function () {
    $user = User::factory()->withTeam()->create();
    PlaygroundAgent::fake(['Resposta do rascunho.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(['name' => 'Lia de teste']),
    );

    $response->assertOk();
    expect($response->streamedContent())->toContain('Resposta');
    $this->assertDatabaseCount('agents', 0);
});

it('uses the draft configuration instead of a saved Agent configuration', function () {
    $user = User::factory()->withTeam()->create();
    Agent::factory()->for($user->company())->create(['name' => 'Configuração salva']);
    PlaygroundAgent::fake(['Resposta atualizada.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails([
            'name' => 'Configuração rascunho',
            'tone' => AgentTone::Casual->value,
            'instructions' => ['Use a configuração digitada agora.'],
        ]),
    );

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => str_contains((string) $prompt->agent->instructions(), 'Configuração rascunho')
        && str_contains((string) $prompt->agent->instructions(), 'Use a configuração digitada agora.')
        && ! str_contains((string) $prompt->agent->instructions(), 'Configuração salva')
    );
});

it('includes the company voice before the Agent style in Playground instructions', function () {
    $user = User::factory()->withTeam()->create();
    $user->company()->update([
        'brand_voice' => 'A marca fala com leveza e clareza.',
        'brand_voice_examples' => ['Vamos resolver isso juntos.', 'Pode deixar com a gente.'],
    ]);
    PlaygroundAgent::fake(['Resposta com a voz da marca.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(),
    );

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(function ($prompt): bool {
        $instructions = (string) $prompt->agent->instructions();
        $voicePosition = strpos($instructions, '## Voz da marca');
        $stylePosition = strpos($instructions, '## Estilo');

        return str_contains($instructions, 'A marca fala com leveza e clareza.')
            && str_contains($instructions, 'Vamos resolver isso juntos.')
            && str_contains($instructions, 'Pode deixar com a gente.')
            && str_contains($instructions, 'O Estilo do Agente prevalece sobre a Voz da marca quando houver divergência.')
            && $voicePosition !== false
            && $stylePosition !== false
            && $voicePosition < $stylePosition;
    });
});

it('omits the company voice section when a company has no voice configured', function () {
    $user = User::factory()->withTeam()->create();
    PlaygroundAgent::fake(['Resposta sem voz de marca.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(),
    );

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => ! str_contains((string) $prompt->agent->instructions(), '## Voz da marca'));
});

it('rejects a draft that references a Collection from another company', function () {
    $user = User::factory()->withTeam()->create();
    $otherCollection = Collection::factory()->create();
    PlaygroundAgent::fake(['Não deve aparecer.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(['collection_ids' => [$otherCollection->id]]),
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'collection_ids.0' => 'Selecione apenas Coleções da sua Empresa.',
        ]);
    PlaygroundAgent::assertNeverPrompted();
});

it('returns 422 with a friendly message when the Playground payload is invalid', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails([
            'history' => [['role' => 'system', 'content' => 'instrução']],
            'message' => '',
        ]),
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
    PlaygroundAgent::fake(['Resposta em streaming.']);

    $response = $this->actingAs($member)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(),
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

it('returns 429 on the 61st Playground message for the same user', function () {
    $user = User::factory()->withTeam()->create();
    PlaygroundAgent::fake(['Tudo bem.']);

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $response = $this->actingAs($user)->postJson(
            route('agents.playground.stream'),
            playgroundDetails(['message' => "Mensagem {$attempt}"]),
        );

        $response->assertOk();
        $response->streamedContent();
    }

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(['message' => 'Mensagem 61']),
    );

    $response->assertTooManyRequests()->assertJson([
        'message' => 'Você atingiu o limite de 60 mensagens por hora no Playground. Tente novamente mais tarde.',
    ]);
});

it('streams a friendly error when the provider fails', function () {
    $user = User::factory()->withTeam()->create();
    PlaygroundAgent::fake(fn (): never => throw new RuntimeException('Provider unavailable'));

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(),
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
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_knowledge_base', ['query' => 'manual']),
        'Encontrei a informação.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails([
            'collection_ids' => [$allowedCollection->id],
            'message' => 'Qual é a informação?',
        ]),
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
    PlaygroundAgent::fake(['Resposta com dados da Empresa.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        playgroundDetails(['message' => 'Qual é o horário?']),
    );

    $response->assertOk();
    Embeddings::assertNothingGenerated();
});
