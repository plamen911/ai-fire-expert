<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
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
            'original_filename' => fake()->word().'.docx',
            'file_hash' => Str::random(64),
            'file_path' => 'documents/'.Str::random(40).'.docx',
            'status' => DocumentStatus::Pending,
            'error_message' => null,
            'uploaded_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Completed,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Processing,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Failed,
            'error_message' => 'Processing failed.',
        ]);
    }
}
