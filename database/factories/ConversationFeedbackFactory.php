<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationFeedback>
 */
class ConversationFeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'message_index' => fake()->numberBetween(0, 20),
            'is_positive' => fake()->boolean(),
        ];
    }
}
