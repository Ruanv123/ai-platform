<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactFieldValue> */
class ContactFieldValueFactory extends Factory
{
    protected $model = ContactFieldValue::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contact_field_id' => ContactField::factory(),
            'contact_id' => Contact::factory(),
            'value' => fake()->word(),
        ];
    }
}
