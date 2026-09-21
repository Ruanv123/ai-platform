<?php

namespace Database\Factories;

use App\Models\GlobalVariable;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GlobalVariable> */
class GlobalVariableFactory extends Factory
{
    protected $model = GlobalVariable::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->word(),
            'value' => fake()->word(),
        ];
    }
}
