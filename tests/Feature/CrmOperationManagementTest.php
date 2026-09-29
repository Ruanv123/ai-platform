<?php

use App\Enums\CrmOperationStatus;
use App\Enums\TeamRole;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    $this->withoutVite();
});

function crmOperationsScenario(): array
{
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
    ]);
    $contact = Contact::factory()->for($team)->create(['name' => 'Ana Silva']);

    return compact('user', 'team', 'integration', 'contact');
}

function createCrmOperation(array $scenario, array $attributes = []): CrmOperation
{
    return CrmOperation::query()->create(array_merge([
        'team_id' => $scenario['team']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'origin' => 'agent',
        'type' => 'guarantee_crm_deal',
        'status' => CrmOperationStatus::Pending,
        'attempts' => 0,
        'token_refresh_attempts' => 0,
        'payload' => ['title' => 'Plano anual'],
        'summary' => ['title' => 'Plano anual'],
        'expires_at' => now()->addDays(30),
    ], $attributes));
}

it('shows recent operations for the current company with sensitive summary fields masked', function () {
    $scenario = crmOperationsScenario();
    $sensitiveField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'CPF',
        'is_sensitive' => true,
    ]);
    $sensitiveField->values()->create([
        'contact_id' => $scenario['contact']->getKey(),
        'value' => '111.222.333-44',
    ]);
    $older = createCrmOperation($scenario, [
        'status' => CrmOperationStatus::Failed,
        'failure_message' => 'Falha ao salvar CPF 111.222.333-44.',
        'created_at' => now()->subMinute(),
        'summary' => [
            'title' => 'Plano anterior',
            'contact_fields' => ['CPF' => '111.222.333-44'],
        ],
    ]);
    $newer = createCrmOperation($scenario, [
        'summary' => [
            'title' => 'Plano anual',
            'contact_fields' => ['CPF' => '999.888.777-66'],
        ],
    ]);
    $otherTeam = Team::factory()->create();
    $otherIntegration = Integration::factory()->for($otherTeam)->create(['type' => 'crm']);
    $otherContact = Contact::factory()->for($otherTeam)->create();
    CrmOperation::query()->create([
        'team_id' => $otherTeam->getKey(),
        'integration_id' => $otherIntegration->getKey(),
        'contact_id' => $otherContact->getKey(),
        'origin' => 'automatic',
        'type' => 'guarantee_crm_deal',
        'status' => CrmOperationStatus::Failed,
        'payload' => [],
        'summary' => ['title' => 'Outra Empresa'],
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($scenario['user'])
        ->get(route('integrations.crm.operations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/crm-operations')
            ->where('operations.total', 2)
            ->where('operations.data.0.id', $newer->getKey())
            ->where('operations.data.0.contactName', 'Ana Silva')
            ->where('operations.data.0.summary.title', 'Plano anual')
            ->where('operations.data.0.summary.contact_fields.CPF', '***')
            ->where('operations.data.1.id', $older->getKey())
            ->where('operations.data.1.summary.contact_fields.CPF', '***')
            ->where('operations.data.1.failureMessage', 'Falha ao salvar CPF ***.'),
        );

    expect($sensitiveField->is_sensitive)->toBeTrue();
});

it('filters the operation list to failures only', function () {
    $scenario = crmOperationsScenario();
    createCrmOperation($scenario, ['status' => CrmOperationStatus::Completed]);
    $failed = createCrmOperation($scenario, [
        'status' => CrmOperationStatus::Failed,
        'failure_message' => 'O CRM recusou o negócio.',
    ]);

    $this->actingAs($scenario['user'])
        ->get(route('integrations.crm.operations.index', ['failed' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('onlyFailed', true)
            ->where('operations.total', 1)
            ->where('operations.data.0.id', $failed->getKey())
            ->where('operations.data.0.failureMessage', 'O CRM recusou o negócio.'),
        );
});

it('allows only owners and admins to view or retry company operations', function () {
    $scenario = crmOperationsScenario();
    $member = User::factory()->create();
    $scenario['team']->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($scenario['team']);
    $failed = createCrmOperation($scenario, ['status' => CrmOperationStatus::Failed]);

    $this->actingAs($member)
        ->get(route('integrations.crm.operations.index'))
        ->assertForbidden();
    $this->actingAs($member)
        ->post(route('integrations.crm.operations.retry', $failed))
        ->assertForbidden();

    $admin = User::factory()->create();
    $scenario['team']->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($scenario['team']);

    $this->actingAs($admin)
        ->get(route('integrations.crm.operations.index'))
        ->assertOk();
});

it('creates a new pending resend operation when a failed operation is retried', function () {
    Queue::fake();
    $scenario = crmOperationsScenario();
    $failed = createCrmOperation($scenario, [
        'status' => CrmOperationStatus::Failed,
        'attempts' => 5,
        'failure_message' => 'Falha definitiva.',
        'summary' => ['title' => 'Plano anual', 'estimated_value' => 1200],
    ]);

    $this->actingAs($scenario['user'])
        ->post(route('integrations.crm.operations.retry', $failed))
        ->assertRedirect(route('integrations.crm.operations.index'))
        ->assertSessionHas('crm_success');

    $retry = CrmOperation::query()->whereKeyNot($failed->getKey())->firstOrFail();

    expect($retry->origin)->toBe('resend')
        ->and($retry->status)->toBe(CrmOperationStatus::Pending)
        ->and($retry->attempts)->toBe(0)
        ->and($retry->payload)->toBe($failed->payload)
        ->and($retry->summary)->toBe($failed->summary)
        ->and($failed->fresh()->status)->toBe(CrmOperationStatus::Failed);

    Queue::assertPushedOn('crm-operations', ProcessCrmOperation::class);
});

it('does not retry an operation that is no longer failed', function () {
    $scenario = crmOperationsScenario();
    $completed = createCrmOperation($scenario, ['status' => CrmOperationStatus::Completed]);

    $this->actingAs($scenario['user'])
        ->post(route('integrations.crm.operations.retry', $completed))
        ->assertStatus(409);

    expect(CrmOperation::query()->count())->toBe(1);
});

it('deletes operations older than thirty days and keeps recent operations', function () {
    $scenario = crmOperationsScenario();
    $expired = createCrmOperation($scenario);
    $expired->forceFill([
        'created_at' => now()->subDays(31),
        'updated_at' => now()->subDays(31),
    ])->save();
    $recent = createCrmOperation($scenario);

    $this->artisan('crm:prune-operations')
        ->assertSuccessful();

    $this->assertDatabaseMissing('crm_operations', ['id' => $expired->getKey()]);
    $this->assertDatabaseHas('crm_operations', ['id' => $recent->getKey()]);
});

it('registers the operation cleanup command in the daily schedule', function () {
    $scheduledCommand = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'crm:prune-operations'));

    expect($scheduledCommand)->not->toBeNull()
        ->and($scheduledCommand->expression)->toBe('45 2 * * *');
});
