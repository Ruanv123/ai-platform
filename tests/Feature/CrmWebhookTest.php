<?php

use App\Crm\CrmCommercialStageService;
use App\Inbox\AgentConversationService;
use App\Jobs\ProcessCrmWebhook;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

function crmWebhookFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path('tests/Fixtures/Crm/RdStation/Webhooks/'.$name.'.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function crmRdApiFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path('tests/Fixtures/Crm/RdStation/'.$name.'.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function createConnectedCrmWebhookIntegration(User $user, string $secret = 'crm-webhook-secret', string $status = 'connected'): Integration
{
    return Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => $status,
        'credentials' => [
            'access_token' => 'crm-access-token',
            'refresh_token' => 'crm-refresh-token',
            'expires_at' => now()->addHour()->getTimestamp(),
            'webhook_secret_hash' => hash('sha256', $secret),
        ],
    ]);
}

function postCrmWebhook(TestCase $testCase, array $payload, string $secret = 'crm-webhook-secret')
{
    return $testCase->postJson(route('webhooks.crm.rd-station'), $payload, [
        'X-RDStation-CRM-Webhook-Key' => $secret,
    ]);
}

it('accepts provider webhook fixtures and queues only the canonical notice once per resource', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    Queue::fake();
    $payload = crmWebhookFixture('deal-updated');

    postCrmWebhook($this, $payload)->assertOk();
    postCrmWebhook($this, $payload)->assertOk();

    Queue::assertPushed(ProcessCrmWebhook::class, 1);
    Queue::assertPushed(ProcessCrmWebhook::class, fn (ProcessCrmWebhook $job): bool => $job->integrationId === $integration->getKey()
        && $job->teamId === $integration->team_id
        && $job->resource === 'deal'
        && $job->externalId === '65b000000000000000000001'
        && $job->deleted === false
        && $job->queue === 'crm-webhooks'
        && ! property_exists($job, 'payload')
    );
    expect(DB::table('crm_operations')->count())->toBe(0);
});

it('rejects an invalid key and quietly acknowledges a disconnected CRM', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user, status: 'disconnected');
    Queue::fake();
    $payload = crmWebhookFixture('contact-updated');

    postCrmWebhook($this, $payload, 'wrong-secret')->assertUnauthorized();
    postCrmWebhook($this, $payload)->assertOk();
    $integration->forceFill(['provider' => 'unknown_crm', 'status' => 'connected'])->save();
    postCrmWebhook($this, $payload)->assertOk();

    Queue::assertNothingPushed();
    expect(DB::table('crm_operations')->count())->toBe(0);
});

it('refreshes a linked person from the CRM without unlinking when its phone changes', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $contact = Contact::factory()->for($user->company())->create(['phone' => '+5511999998888']);
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    Http::fake(fn (Request $request) => Http::response(crmRdApiFixture('contact')));
    Http::preventStrayRequests();
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('contact-updated'))->assertOk();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/crm/v2/contacts/67c000000000000000000002'));
    expect($link->fresh()->contact_id)->toBe($contact->getKey())
        ->and(CrmContactLink::query()->where('contact_id', $contact->getKey())->count())->toBe(1);
});

it('deletes the CRM person link and its linked deals when RD reports a deletion', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $contact = Contact::factory()->for($user->company())->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => '65b000000000000000000001',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-1',
        'status' => 'open',
    ]);
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('contact-deleted'))->assertOk();

    $this->assertDatabaseMissing('crm_contact_links', ['id' => $link->getKey()]);
    $this->assertDatabaseMissing('crm_linked_deals', ['external_deal_id' => '65b000000000000000000001']);
    $this->assertDatabaseHas('contacts', ['id' => $contact->getKey()]);
});

it('updates the canonical linked deal using the current CRM record', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $contact = Contact::factory()->for($user->company())->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    $linkedDeal = CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => '65b000000000000000000001',
        'pipeline_id' => 'old-pipeline',
        'stage_id' => 'old-stage',
        'status' => 'open',
        'value' => 100,
        'owner_id' => 'old-owner',
    ]);
    Http::fake(fn (Request $request) => Http::response(crmRdApiFixture('deal-updated')));
    Http::preventStrayRequests();
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('deal-updated'))->assertOk();

    expect($linkedDeal->fresh()->pipeline_id)->toBe('65a000000000000000000001')
        ->and($linkedDeal->fresh()->stage_id)->toBe('65a000000000000000000002')
        ->and($linkedDeal->fresh()->status)->toBe('open')
        ->and($linkedDeal->fresh()->value)->toBe(2800.0)
        ->and($linkedDeal->fresh()->owner_id)->toBe('65a000000000000000000003')
        ->and(CrmLinkedDeal::query()->count())->toBe(1);
});

it('links a deal to an existing contact by normalized phone without creating a contact', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $contact = Contact::factory()->for($user->company())->create(['phone' => '+5511999998888']);
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/crm/v2/deals/65b000000000000000000005')) {
            return Http::response(crmRdApiFixture('deal-created'));
        }

        if (str_ends_with($request->url(), '/crm/v2/contacts/67c000000000000000000005')) {
            return Http::response(crmRdApiFixture('person-created'));
        }

        throw new RuntimeException('Unexpected RD Station request: '.$request->url());
    });
    Http::preventStrayRequests();
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('deal-created'))->assertOk();

    expect(Contact::query()->count())->toBe(1)
        ->and(CrmContactLink::query()->where('contact_id', $contact->getKey())->value('external_person_id'))
        ->toBe('67c000000000000000000005')
        ->and(CrmLinkedDeal::query()->where('external_deal_id', '65b000000000000000000005')->exists())
        ->toBeTrue();
});

it('discards a CRM deal when its person has no existing contact with the current phone', function () {
    $user = User::factory()->withTeam()->create();
    createConnectedCrmWebhookIntegration($user);
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/crm/v2/deals/65b000000000000000000005')) {
            return Http::response(crmRdApiFixture('deal-created'));
        }

        if (str_ends_with($request->url(), '/crm/v2/contacts/67c000000000000000000005')) {
            return Http::response(crmRdApiFixture('person-created'));
        }

        throw new RuntimeException('Unexpected RD Station request: '.$request->url());
    });
    Http::preventStrayRequests();
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('deal-created'))->assertOk();

    expect(Contact::query()->count())->toBe(0)
        ->and(CrmContactLink::query()->count())->toBe(0)
        ->and(CrmLinkedDeal::query()->count())->toBe(0);
});

it('removes a canonical linked deal after an RD deletion notice', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $contact = Contact::factory()->for($user->company())->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => '65b000000000000000000001',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-1',
        'status' => 'open',
    ]);
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('deal-deleted'))->assertOk();

    $this->assertDatabaseMissing('crm_linked_deals', ['external_deal_id' => '65b000000000000000000001']);
});

it('pauses every Agent channel when the webhook moves a linked deal into a commercial stage', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $integration->forceFill([
        'crm_settings' => ['commercial_stage_ids' => ['65a000000000000000000002']],
    ])->save();
    $contact = Contact::factory()->for($user->company())->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => '65b000000000000000000001',
        'pipeline_id' => 'old-pipeline',
        'stage_id' => 'stage-before-commercial',
        'status' => 'open',
    ]);
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->connected()->create(['agent_id' => $agent->getKey()]);
    Http::fake(fn (Request $request) => Http::response(crmRdApiFixture('deal-updated')));
    Http::preventStrayRequests();
    config(['queue.default' => 'sync']);

    postCrmWebhook($this, crmWebhookFixture('deal-updated'))->assertOk();

    $contact->refresh();
    $responseAttempted = false;
    expect($contact->crm_agent_paused)->toBeTrue()
        ->and($contact->human_waiting)->toBeTrue()
        ->and(app(AgentConversationService::class)->isPaused($channel, (int) $contact->getKey()))->toBeTrue()
        ->and(app(AgentConversationService::class)->runIfUnpaused($channel, $contact, function () use (&$responseAttempted): void {
            $responseAttempted = true;
        }))->toBeFalse()
        ->and($responseAttempted)->toBeFalse();
});

it('re-evaluates linked contacts when commercial stages are changed and restricts edits to integration managers', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $integration = createConnectedCrmWebhookIntegration($user);
    $integration->forceFill([
        'crm_metadata' => ['pipelines' => [[
            'id' => 'pipeline-1',
            'stages' => [['id' => 'stage-commercial', 'name' => 'Proposta']],
        ]]],
        'crm_settings' => ['commercial_stage_ids' => []],
    ])->save();
    $contact = Contact::factory()->for($team)->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $team->getKey(),
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => 'person-commercial-stage',
    ]);
    CrmLinkedDeal::query()->create([
        'team_id' => $team->getKey(),
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => 'deal-commercial-stage',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-commercial',
        'status' => 'open',
    ]);

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => 'member']);
    $this->actingAs($member)
        ->put(route('integrations.crm.commercial-stages.update'), ['commercial_stage_ids' => ['stage-commercial']])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('integrations.crm.commercial-stages.update'), ['commercial_stage_ids' => ['stage-commercial']])
        ->assertRedirect(route('integrations.show', 'crm'));
    expect($contact->fresh()->crm_agent_paused)->toBeTrue()
        ->and($integration->fresh()->crm_settings['commercial_stage_ids'])->toBe(['stage-commercial']);

    $this->actingAs($user)
        ->put(route('integrations.crm.commercial-stages.update'), ['commercial_stage_ids' => []])
        ->assertRedirect(route('integrations.show', 'crm'));
    expect($contact->fresh()->crm_agent_paused)->toBeFalse()
        ->and($contact->fresh()->human_waiting)->toBeFalse();
});

it('suppresses a manual CRM return until the contact leaves and re-enters a commercial stage', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $integration->forceFill(['crm_settings' => ['commercial_stage_ids' => ['stage-commercial']]])->save();
    $contact = Contact::factory()->for($user->company())->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => 'person-manual-return',
    ]);
    $firstDeal = CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => 'deal-first-commercial',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-commercial',
        'status' => 'open',
    ]);
    $secondDeal = CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => 'deal-second-commercial',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-commercial',
        'status' => 'open',
    ]);
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->connected()->create(['agent_id' => $agent->getKey()]);
    $stages = app(CrmCommercialStageService::class);
    $stages->reconcileContact($contact->team_id, (int) $contact->getKey());

    app(AgentConversationService::class)->returnToAgent($contact, $channel, $user);
    $stages->reconcileContact($contact->team_id, (int) $contact->getKey());
    expect($contact->fresh()->crm_agent_paused)->toBeFalse()
        ->and($contact->fresh()->crm_pause_suppressed)->toBeTrue();

    $firstDeal->forceFill(['status' => 'lost'])->save();
    $stages->reconcileDealChange($firstDeal->fresh(), 'open');
    expect($contact->fresh()->crm_agent_paused)->toBeFalse();

    $secondDeal->forceFill(['stage_id' => 'stage-before-commercial'])->save();
    $stages->reconcileDealChange($secondDeal->fresh(), 'open');
    expect($contact->fresh()->crm_pause_suppressed)->toBeFalse();

    $secondDeal->forceFill(['stage_id' => 'stage-commercial'])->save();
    $stages->reconcileDealChange($secondDeal->fresh(), 'open');
    expect($contact->fresh()->crm_agent_paused)->toBeTrue();
});

it('clears CRM pauses for linked contacts when the integration is deleted', function () {
    $user = User::factory()->withTeam()->create();
    $integration = createConnectedCrmWebhookIntegration($user);
    $integration->forceFill(['crm_settings' => ['commercial_stage_ids' => ['stage-commercial']]])->save();
    $contact = Contact::factory()->for($user->company())->create();
    $link = CrmContactLink::query()->create([
        'team_id' => $contact->team_id,
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => 'person-deleted-integration',
    ]);
    CrmLinkedDeal::query()->create([
        'team_id' => $contact->team_id,
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => 'deal-deleted-integration',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-commercial',
        'status' => 'open',
    ]);
    app(CrmCommercialStageService::class)->reconcileContact($contact->team_id, (int) $contact->getKey());

    $this->actingAs($user)
        ->delete(route('integrations.destroy', $integration))
        ->assertRedirect(route('integrations.index'));

    expect($contact->fresh()->crm_agent_paused)->toBeFalse()
        ->and($contact->fresh()->human_waiting)->toBeFalse();
});
