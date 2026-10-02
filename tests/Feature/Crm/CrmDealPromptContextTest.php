<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Agents\WhatsAppAgent;
use App\Ecommerce\ProductCatalogSearch;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

function crmDealPromptScenario(
    ?Team $team = null,
    string $integrationStatus = 'connected',
    bool $createContactLink = true,
): array {
    $team ??= Team::factory()->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => $integrationStatus,
        'crm_metadata' => crmDealPromptMetadata(),
    ]);
    $agent = Agent::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create([
        'phone' => '+5511999998888',
    ]);
    $contactLink = $createContactLink
        ? CrmContactLink::query()->create([
            'team_id' => $team->getKey(),
            'contact_id' => $contact->getKey(),
            'integration_id' => $integration->getKey(),
            'external_person_id' => '67c000000000000000000001',
        ])
        : null;

    return compact('team', 'integration', 'agent', 'contact', 'contactLink');
}

function crmDealPromptMetadata(): array
{
    return [
        'pipelines' => [[
            'id' => 'pipeline-sales',
            'name' => 'Vendas consultivas',
            'stages' => [
                ['id' => 'stage-proposal', 'name' => 'Proposta enviada'],
                ['id' => 'stage-negotiation', 'name' => 'Negociação'],
                ['id' => 'stage-won', 'name' => 'Ganho'],
                ['id' => 'stage-lost', 'name' => 'Perdido'],
            ],
        ], [
            'id' => 'pipeline-renewal',
            'name' => 'Renovações',
            'stages' => [['id' => 'stage-review', 'name' => 'Em análise']],
        ]],
        'owners' => [
            ['id' => 'owner-carlos', 'name' => 'Carlos Mendes'],
            ['id' => 'owner-ana', 'name' => 'Ana Costa'],
        ],
    ];
}

function createCrmDealPromptRecord(
    CrmContactLink $contactLink,
    string $pipelineId,
    string $stageId,
    string $status,
    ?string $ownerId,
    float $value,
): CrmLinkedDeal {
    static $dealNumber = 0;
    $dealNumber++;

    return CrmLinkedDeal::query()->create([
        'team_id' => $contactLink->team_id,
        'crm_contact_link_id' => $contactLink->getKey(),
        'integration_id' => $contactLink->integration_id,
        'external_deal_id' => '67c'.str_pad((string) $dealNumber, 21, '0', STR_PAD_LEFT),
        'pipeline_id' => $pipelineId,
        'stage_id' => $stageId,
        'status' => $status,
        'value' => $value,
        'owner_id' => $ownerId,
        'provider_updated_at' => now(),
    ]);
}

function makeCrmPromptWhatsAppAgent(Agent $agent, Contact $contact): WhatsAppAgent
{
    return new WhatsAppAgent(
        $agent,
        collect(),
        app(AgentInstructionTemplate::class),
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        contact: $contact,
    );
}

it('includes every open linked deal without values in the WhatsApp prompt', function (): void {
    $scenario = crmDealPromptScenario();
    createCrmDealPromptRecord($scenario['contactLink'], 'pipeline-sales', 'stage-proposal', 'open', 'owner-carlos', 918273.64);
    createCrmDealPromptRecord($scenario['contactLink'], 'pipeline-renewal', 'stage-review', 'open', 'owner-ana', 837261.45);
    createCrmDealPromptRecord($scenario['contactLink'], 'pipeline-sales', 'stage-won', 'won', 'owner-carlos', 746352.18);
    createCrmDealPromptRecord($scenario['contactLink'], 'pipeline-sales', 'stage-lost', 'lost', 'owner-ana', 625341.09);
    Http::preventStrayRequests();
    WhatsAppAgent::fake(['Posso ajudar com sua proposta.']);

    makeCrmPromptWhatsAppAgent($scenario['agent'], $scenario['contact'])->prompt('Como está minha proposta?');

    WhatsAppAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'Funil de vendas: Vendas consultivas; Etapa do funil: Proposta enviada; Situação: Aberto; Responsável: Carlos Mendes')
            && str_contains($instructions, 'Funil de vendas: Renovações; Etapa do funil: Em análise; Situação: Aberto; Responsável: Ana Costa')
            && ! str_contains($instructions, 'Ganho')
            && ! str_contains($instructions, 'Perdido')
            && ! str_contains($instructions, '918273.64')
            && ! str_contains($instructions, '837261.45')
            && ! str_contains($instructions, '746352.18')
            && ! str_contains($instructions, '625341.09');
    });
});

it('leaves instructions unchanged without a connected CRM contact link or open deal', function (
    string $integrationStatus,
    bool $createContactLink,
    ?string $dealStatus,
): void {
    $scenario = crmDealPromptScenario(
        integrationStatus: $integrationStatus,
        createContactLink: $createContactLink,
    );

    if ($dealStatus !== null && $scenario['contactLink'] !== null) {
        createCrmDealPromptRecord($scenario['contactLink'], 'pipeline-sales', 'stage-proposal', $dealStatus, 'owner-carlos', 918273.64);
    }

    Http::preventStrayRequests();
    $instructionTemplate = app(AgentInstructionTemplate::class);
    $promptWithContact = makeCrmPromptWhatsAppAgent($scenario['agent'], $scenario['contact'])->instructions();

    expect((string) $promptWithContact)->toBe($instructionTemplate->render($scenario['agent']));
})->with([
    'disconnected integration' => ['error', true, 'open'],
    'no contact link' => ['connected', false, null],
    'only closed deals' => ['connected', true, 'won'],
]);

it('includes locally linked deal context in the Playground for a matching contact phone', function (): void {
    $user = User::factory()->withTeam()->create();
    $scenario = crmDealPromptScenario($user->company());
    createCrmDealPromptRecord($scenario['contactLink'], 'pipeline-sales', 'stage-negotiation', 'open', 'owner-carlos', 918273.64);
    Http::preventStrayRequests();
    PlaygroundAgent::fake(['Vamos acompanhar a negociação.']);

    $response = $this->actingAs($user)->postJson(route('agents.playground.stream'), [
        'name' => 'Lia',
        'objective' => AgentObjective::Sales->value,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'crm_lookup_phone' => $scenario['contact']->phone,
        'history' => [],
        'message' => 'Como está minha proposta?',
    ]);

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'Funil de vendas: Vendas consultivas; Etapa do funil: Negociação; Situação: Aberto; Responsável: Carlos Mendes')
            && ! str_contains($instructions, '918273.64');
    });
});
