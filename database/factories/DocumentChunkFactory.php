<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentChunk>
 */
class DocumentChunkFactory extends Factory
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
            'chunk_index' => fake()->randomDigit(),
            'content' => fake()->paragraphs(3, true),
            'embedding' => array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 1536)),
        ];
    }
}
