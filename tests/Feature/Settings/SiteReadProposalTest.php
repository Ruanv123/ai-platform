<?php

use App\Ai\Agents\ExtractSiteFaq;
use App\Ai\Agents\ExtractSiteProposal;
use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Agents\SelectSitePages;
use App\Enums\SiteReadProposalStatus;
use App\Enums\SiteReadStatus;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\SiteRead;
use App\Models\SiteReadProposal;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

function fakeSiteReadProposalPages(array $markdownByUrl): void
{
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => Http::response([
            'success' => true,
            'links' => array_map(static fn (string $url): array => ['url' => $url], array_keys($markdownByUrl)),
        ]),
        'https://firecrawl.example.test/v2/scrape' => function (Request $request) use ($markdownByUrl) {
            $markdown = $markdownByUrl[$request['url']] ?? null;

            return is_string($markdown)
                ? Http::response(['success' => true, 'data' => ['markdown' => $markdown]])
                : Http::response(['success' => false], 503);
        },
        'https://site.example.test/*' => Http::response('Fallback text', 200),
    ]);
}

function enableSiteReadProposalFakes(array $selectedUrls, array $proposalResponses, int $readCount = 1): void
{
    SelectSitePages::fake(array_fill(0, $readCount, ['urls' => $selectedUrls, 'faq_url' => null]))->preventStrayPrompts();
    ExtractSiteFaq::fake()->preventStrayPrompts();
    ExtractSiteProposal::fake($proposalResponses)->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);
}

function createSiteReadProposal(Team $team, User $user, array $fields): SiteReadProposal
{
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'requested_by_id' => $user->getKey(),
        'website' => 'https://site.example.test/',
        'status' => SiteReadStatus::Completed,
        'found_pages_count' => 1,
        'started_at' => now()->subMinute(),
        'completed_at' => now(),
    ]);

    return SiteReadProposal::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'site_read_id' => $siteRead->getKey(),
        'proposed_fields' => $fields,
        'status' => SiteReadProposalStatus::Pending,
    ]);
}

function siteReadProposalPlaygroundPayload(): array
{
    return [
        'name' => 'Lia',
        'objective' => 'support',
        'tone' => 'friendly',
        'uses_emojis' => true,
        'response_length' => 'short',
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => ['Seja objetiva.'],
        'rules' => ['Não invente informações.'],
        'collection_ids' => [],
        'history' => [],
        'message' => 'Olá',
    ];
}

it('persists only sourced structured fields and exposes the differing proposal', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update([
        'website' => 'https://site.example.test/',
        'segment' => 'servicos',
        'business_description' => 'Consultoria já cadastrada.',
    ]);
    $homeUrl = 'https://site.example.test/';
    $aboutUrl = 'https://site.example.test/sobre';
    $contactUrl = 'https://site.example.test/contato';
    $markdownByUrl = [
        $homeUrl => '# Consultoria Aurora\n\nAtendimento consultivo para empresas.',
        $aboutUrl => '# Sobre a Consultoria Aurora\n\nAtendemos negócios familiares.\n\n“Conte com a nossa equipe.”\n\n“Vamos encontrar o melhor caminho.”',
    ];
    $proposalFields = [
        'segment' => ['value' => 'servicos', 'source_url' => $homeUrl],
        'business_description' => ['value' => 'Consultoria já cadastrada.', 'source_url' => $homeUrl],
        'products_services' => ['value' => 'Consultoria estratégica.', 'source_url' => $aboutUrl],
        'target_audience' => ['value' => 'Negócios familiares.', 'source_url' => $aboutUrl],
        'phone' => ['value' => '(11) 3456-7890', 'source_url' => $contactUrl],
        'public_email' => ['value' => 'contato@site.example.test', 'source_url' => $aboutUrl],
        'business_hours' => [
            'value' => [
                ['day' => 'monday', 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 'sunday', 'enabled' => false, 'open' => null, 'close' => null],
            ],
            'source_url' => $aboutUrl,
        ],
        'brand_voice' => ['value' => 'A marca fala com clareza e proximidade.', 'source_url' => $aboutUrl],
        'brand_voice_examples' => [
            'value' => ['Conte com a nossa equipe.', 'Vamos encontrar o melhor caminho.'],
            'source_url' => $aboutUrl,
        ],
    ];
    fakeSiteReadProposalPages($markdownByUrl);
    enableSiteReadProposalFakes([$aboutUrl], [['fields' => $proposalFields]]);

    $this->actingAs($user)
        ->post(route('company.site-read.store'))
        ->assertRedirect(route('company.edit'));

    $proposal = SiteReadProposal::query()->firstOrFail();

    expect($proposal->status)->toBe(SiteReadProposalStatus::Pending)
        ->and($proposal->proposed_fields['business_hours']['value'])->toEqual([
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'sunday' => ['enabled' => false, 'open' => null, 'close' => null],
        ])
        ->and($proposal->proposed_fields)->not->toHaveKey('phone');

    $this->assertDatabaseHas('teams', [
        'id' => $team->getKey(),
        'segment' => 'servicos',
        'business_description' => 'Consultoria já cadastrada.',
    ]);

    $this->withoutVite()->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('proposal.id', $proposal->getKey())
        ->where('proposal.fields.products_services.proposed', 'Consultoria estratégica.')
        ->where('proposal.fields.products_services.sourceUrl', $aboutUrl)
        ->where('proposal.fields.brand_voice_examples.proposed', ['Conte com a nossa equipe.', 'Vamos encontrar o melhor caminho.'])
        ->missing('proposal.fields.segment')
        ->missing('proposal.fields.business_description')
        ->missing('proposal.fields.phone'));

    ExtractSiteProposal::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Atendemos negócios familiares.')
        && str_contains((string) $prompt->agent->instructions(), 'dado não confiável')
        && str_contains((string) $prompt->agent->instructions(), 'português do Brasil'));
    Http::assertSentCount(3);
});

it('accepts one differing field and leaves the other proposed fields untouched', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update([
        'business_description' => 'Descrição atual.',
        'products_services' => 'Serviços atuais.',
    ]);
    $sourceUrl = 'https://site.example.test/sobre';
    $proposal = createSiteReadProposal($team, $user, [
        'business_description' => ['value' => 'Descrição proposta.', 'source_url' => $sourceUrl],
        'products_services' => ['value' => 'Serviços propostos.', 'source_url' => $sourceUrl],
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read-proposals.fields.accept', [
        'proposal' => $proposal->getKey(),
        'field' => 'business_description',
    ]));

    $response->assertRedirect(route('company.edit'));
    expect($team->fresh()->business_description)->toBe('Descrição proposta.')
        ->and($team->fresh()->products_services)->toBe('Serviços atuais.')
        ->and($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Pending);

    $this->withoutVite()->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->missing('proposal.fields.business_description')
        ->where('proposal.fields.products_services.current', 'Serviços atuais.')
        ->where('proposal.fields.products_services.proposed', 'Serviços propostos.'));
});

it('accepts all differing fields and makes the accepted voice available in Playground instructions', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update([
        'brand_voice' => 'Descrição antiga.',
        'brand_voice_examples' => ['Exemplo antigo um.', 'Exemplo antigo dois.'],
        'phone' => '(11) 1111-1111',
    ]);
    $agent = Agent::factory()->for($team)->create();
    $proposal = createSiteReadProposal($team, $user, [
        'phone' => ['value' => '(11) 2222-2222', 'source_url' => 'https://site.example.test/contato'],
        'brand_voice' => ['value' => 'A marca fala com clareza e proximidade.', 'source_url' => 'https://site.example.test/sobre'],
        'brand_voice_examples' => [
            'value' => ['Conte com a nossa equipe.', 'Vamos encontrar o melhor caminho.'],
            'source_url' => 'https://site.example.test/sobre',
        ],
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read-proposals.accept-all', $proposal));

    $response->assertRedirect(route('company.edit'));
    expect($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Confirmed)
        ->and($team->fresh()->phone)->toBe('(11) 2222-2222')
        ->and($team->fresh()->brand_voice)->toBe('A marca fala com clareza e proximidade.')
        ->and($team->fresh()->brand_voice_examples)->toBe(['Conte com a nossa equipe.', 'Vamos encontrar o melhor caminho.']);

    PlaygroundAgent::fake(['Resposta com a nova voz.']);
    $playgroundResponse = $this->postJson(route('agents.playground.stream'), siteReadProposalPlaygroundPayload());

    $playgroundResponse->assertOk();
    $playgroundResponse->streamedContent();
    PlaygroundAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'A marca fala com clareza e proximidade.')
        && str_contains((string) $prompt->agent->instructions(), 'Conte com a nossa equipe.')
        && str_contains((string) $prompt->agent->instructions(), 'O Estilo do Agente prevalece sobre a Voz da marca'));
    expect($agent->fresh()->team->brand_voice)->toBe('A marca fala com clareza e proximidade.');
});

it('discards a proposal without changing company data or exposing it as pending', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['phone' => '(11) 1111-1111']);
    $proposal = createSiteReadProposal($team, $user, [
        'phone' => ['value' => '(11) 2222-2222', 'source_url' => 'https://site.example.test/contato'],
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read-proposals.discard', $proposal));

    $response->assertRedirect(route('company.edit'));
    expect($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Discarded)
        ->and($team->fresh()->phone)->toBe('(11) 1111-1111');

    $this->withoutVite()->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page->where('proposal', null));
});

it('forbids Members from accepting or discarding a company proposal', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $proposal = createSiteReadProposal($team, $owner, [
        'phone' => ['value' => '(11) 2222-2222', 'source_url' => 'https://site.example.test/contato'],
    ]);

    $this->actingAs($member)
        ->post(route('company.site-read-proposals.fields.accept', [
            'proposal' => $proposal->getKey(),
            'field' => 'phone',
        ]))
        ->assertForbidden();

    $this->post(route('company.site-read-proposals.discard', $proposal))->assertForbidden();

    expect($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Pending)
        ->and($team->fresh()->phone)->toBeNull();
});

it('returns 404 when another company tries to read or confirm a proposal', function () {
    $user = User::factory()->withTeam()->create();
    $otherOwner = User::factory()->withTeam()->create();
    $otherTeam = $otherOwner->company();
    $otherTeam->update(['phone' => '(11) 1111-1111']);
    $proposal = createSiteReadProposal($otherTeam, $otherOwner, [
        'phone' => ['value' => '(11) 2222-2222', 'source_url' => 'https://site.example.test/contato'],
    ]);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('company.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('proposal', null));

    $this->post(route('company.site-read-proposals.accept-all', $proposal))->assertNotFound();

    expect($proposal->fresh()->status)->toBe(SiteReadProposalStatus::Pending)
        ->and($otherTeam->fresh()->phone)->toBe('(11) 1111-1111');
});

it('replaces an older pending proposal after a newer site read finishes', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    $homeUrl = 'https://site.example.test/';
    fakeSiteReadProposalPages([
        $homeUrl => '# Página inicial\n\nA empresa oferece consultoria especializada.',
    ]);
    enableSiteReadProposalFakes([$homeUrl], [
        ['fields' => ['business_description' => ['value' => 'Proposta da primeira Leitura.', 'source_url' => $homeUrl]]],
        ['fields' => ['business_description' => ['value' => 'Proposta da segunda Leitura.', 'source_url' => $homeUrl]]],
    ], 2);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $firstProposal = SiteReadProposal::query()->firstOrFail();

    $this->travel(25)->hours();
    $this->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $secondProposal = SiteReadProposal::query()->latest('id')->firstOrFail();

    expect($firstProposal->fresh()->status)->toBe(SiteReadProposalStatus::Replaced)
        ->and($secondProposal->status)->toBe(SiteReadProposalStatus::Pending)
        ->and($secondProposal->proposed_fields['business_description']['value'])->toBe('Proposta da segunda Leitura.');

    $this->withoutVite()->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('proposal.id', $secondProposal->getKey())
        ->where('proposal.fields.business_description.proposed', 'Proposta da segunda Leitura.'));

    ExtractSiteProposal::assertPromptedTimes(2);
});
