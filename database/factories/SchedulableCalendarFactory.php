<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchedulableCalendar> */
class SchedulableCalendarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'integration_id' => Integration::factory()->state(['type' => 'agenda', 'provider' => 'google']),
            'google_calendar_id' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'display_description' => fake()->sentence(),
            'duration_minutes' => 30,
            'minimum_notice_minutes' => 120,
            'business_hours' => null,
        ];
    }
}
