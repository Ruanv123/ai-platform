<?php

use App\Crm\CrmCommercialStageService;
use App\Events\CrmRealtimeNotification;
use App\Events\HumanConversationUpdated;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Support\Facades\Event;

it('broadcasts the pause notification to the team inbox after the CRM transition commits', function () {
    $team = Team::factory()->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata' => ['lost_reasons' => [['id' => 'reason-1', 'name' => 'Sem orçamento']]],
        'crm_settings' => ['commercial_stage_ids' => ['stage-commercial']],
    ]);
    $contact = Contact::factory()->for($team)->create(['name' => 'Ana Silva']);
    $link = CrmContactLink::query()->create([
        'team_id' => $team->getKey(),
        'contact_id' => $contact->getKey(),
        'integration_id' => $integration->getKey(),
        'external_person_id' => 'person-broadcast',
    ]);
    $deal = CrmLinkedDeal::query()->create([
        'team_id' => $team->getKey(),
        'crm_contact_link_id' => $link->getKey(),
        'integration_id' => $integration->getKey(),
        'external_deal_id' => 'deal-broadcast',
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-commercial',
        'status' => 'open',
    ]);
    Event::fake([CrmRealtimeNotification::class, HumanConversationUpdated::class]);

    app(CrmCommercialStageService::class)->reconcileDealChange($deal, null);

    $notification = null;
    Event::assertDispatched(CrmRealtimeNotification::class, function (CrmRealtimeNotification $event) use (&$notification, $team, $contact): bool {
        $notification = $event;

        return $event->teamId === $team->getKey()
            && $event->contactId === $contact->getKey()
            && $event->type === 'crm_paused';
    });
    expect($notification->broadcastAs())->toBe('crm.notification')
        ->and($notification->broadcastWith())->toMatchArray([
            'teamId' => $team->getKey(),
            'contactId' => $contact->getKey(),
            'type' => 'crm_paused',
            'message' => 'IA pausada pelo CRM para Ana Silva.',
        ])
        ->and($notification->broadcastOn()[0]->name)->toBe('private-team.'.$team->getKey().'.inbox');

    $deal->forceFill(['status' => 'lost'])->save();
    app(CrmCommercialStageService::class)->reconcileDealChange($deal->fresh(), 'open', 'reason-1');

    $lossNotification = null;
    Event::assertDispatched(CrmRealtimeNotification::class, function (CrmRealtimeNotification $event) use (&$lossNotification): bool {
        if ($event->type !== 'deal_lost') {
            return false;
        }

        $lossNotification = $event;

        return str_contains($event->message, 'Motivo de perda: Sem orçamento.');
    });
    expect($lossNotification->broadcastWith()['type'])->toBe('deal_lost')
        ->and($contact->fresh()->crm_agent_paused)->toBeFalse();
});
