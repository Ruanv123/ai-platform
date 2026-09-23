<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'team_id' => Team::factory(),
            'schedulable_calendar_id' => SchedulableCalendar::factory(),
            'contact_id' => Contact::factory(),
            'google_event_id' => fake()->unique()->uuid(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'reason' => fake()->sentence(),
            'status' => 'booked',
        ];
    }
}
