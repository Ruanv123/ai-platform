<?php

use App\Enums\AgentObjective;
use App\Models\Agent;
use App\Models\User;

it('backfills existing agents as their first published version', function (): void {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $salesAgent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'search_products' => null,
    ]);
    $supportAgent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Support,
        'search_products' => false,
    ]);
    $migration = require database_path('migrations/2026_09_29_040627_create_agent_versions_table.php');

    $migration->down();
    $migration->up();

    $salesVersion = $salesAgent->fresh()->publishedVersion();
    $supportVersion = $supportAgent->fresh()->publishedVersion();

    expect($salesVersion)->not->toBeNull()
        ->and($salesVersion->number)->toBe(1)
        ->and($salesVersion->objective)->toBe(AgentObjective::Sales)
        ->and($salesVersion->ecommerce_tools)->toBe(['search_products' => null])
        ->and($salesVersion->published_at)->not->toBeNull()
        ->and($supportVersion)->not->toBeNull()
        ->and($supportVersion->objective)->toBe(AgentObjective::Support)
        ->and($supportVersion->ecommerce_tools)->toBe(['search_products' => false]);
});
