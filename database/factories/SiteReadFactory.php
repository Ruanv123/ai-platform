<?php

namespace Database\Factories;

use App\Enums\SiteReadStatus;
use App\Models\SiteRead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteReadFactory extends Factory
{
    protected $model = SiteRead::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'requested_by_id' => User::factory(),
            'website' => fake()->url(),
            'status' => SiteReadStatus::Completed,
            'found_pages_count' => 1,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}
