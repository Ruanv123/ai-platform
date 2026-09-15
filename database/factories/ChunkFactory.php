<?php

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'team_id' => null,
            'collection_id' => null,
            'position' => 0,
            'content' => fake()->paragraph(),
            'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Chunk $chunk): void {
            $chunk->team_id = $chunk->document->team_id;
            $chunk->collection_id = $chunk->document->collection_id;
        });
    }
}
