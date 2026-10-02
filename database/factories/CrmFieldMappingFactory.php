<?php

namespace Database\Factories;

use App\Models\ContactField;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmFieldMappingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'integration_id' => Integration::factory(),
            'contact_field_id' => ContactField::factory(),
            'crm_field_id' => fake()->unique()->slug(),
            'direction' => 'to_crm',
            'option_mappings' => null,
        ];
    }
}
