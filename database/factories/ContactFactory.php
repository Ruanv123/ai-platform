<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'phone' => fake()->unique()->numerify('+55119########'),
            'name' => fake()->name(),
        ];
    }

    /**
     * Indicate that the contact has no WhatsApp name.
     */
    public function withoutName(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => null,
        ]);
    }

    /**
     * Set the name supplied by WhatsApp.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
        ]);
    }
}
