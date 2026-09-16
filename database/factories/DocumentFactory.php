<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Collection;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => null,
            'collection_id' => Collection::factory(),
            'type' => DocumentType::Text,
            'status' => DocumentStatus::Pending,
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'faq_items' => null,
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'url' => null,
            'error_message' => null,
            'size_bytes' => 0,
            'ingestion_token' => fake()->uuid(),
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the document is waiting for ingestion.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Pending,
        ]);
    }

    /**
     * Configure the factory to copy the tenant from the collection.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Document $document): void {
            $document->team_id = $document->collection->team_id;
            $document->size_bytes = $document->size_bytes ?: strlen($document->content);
        });
    }
}
