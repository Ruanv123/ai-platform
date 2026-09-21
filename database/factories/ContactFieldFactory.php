<?php

namespace Database\Factories;

use App\Models\ContactField;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactField> */
class ContactFieldFactory extends Factory
{
    protected $model = ContactField::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->word(),
            'type' => 'text',
            'options' => null,
        ];
    }
}
