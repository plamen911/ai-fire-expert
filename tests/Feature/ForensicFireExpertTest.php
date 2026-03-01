<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\ForensicFireExpert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForensicFireExpertTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructions_contain_user_name_and_position(): void
    {
        $user = User::factory()->create([
            'name' => 'Иван Петров',
            'position' => 'Главен инспектор ПБЗН',
        ]);

        $agent = new ForensicFireExpert;
        $agent->forUser($user);

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Иван Петров', $instructions);
        $this->assertStringContainsString('Главен инспектор ПБЗН', $instructions);
    }

    public function test_instructions_do_not_ask_for_expert_data(): void
    {
        $user = User::factory()->create();

        $agent = new ForensicFireExpert;
        $agent->forUser($user);

        $instructions = $agent->instructions();

        $this->assertStringNotContainsString('Данни за експерта', $instructions);
    }

    public function test_instructions_use_defaults_when_no_user(): void
    {
        $agent = new ForensicFireExpert;

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Непознат', $instructions);
    }

    public function test_instructions_contain_ai_bot_identity(): void
    {
        $agent = new ForensicFireExpert;

        $instructions = $agent->instructions();

        $this->assertStringContainsString('бот с изкуствен интелект', $instructions);
    }

    public function test_instructions_handle_user_without_position(): void
    {
        $user = User::factory()->create([
            'name' => 'Мария Иванова',
            'position' => null,
        ]);

        $agent = new ForensicFireExpert;
        $agent->forUser($user);

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Мария Иванова', $instructions);
        $this->assertStringContainsString('Длъжност:', $instructions);
    }
}
