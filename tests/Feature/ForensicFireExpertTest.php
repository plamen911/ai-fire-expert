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

    public function test_instructions_do_not_contain_expert_data_section(): void
    {
        $agent = new ForensicFireExpert;

        $instructions = $agent->instructions();

        $this->assertStringNotContainsString('Данни за текущия експерт', $instructions);
        $this->assertStringNotContainsString('{{EXPERT_NAME}}', $instructions);
        $this->assertStringNotContainsString('{{EXPERT_POSITION}}', $instructions);
    }

    public function test_instructions_contain_ai_bot_identity(): void
    {
        $agent = new ForensicFireExpert;

        $instructions = $agent->instructions();

        $this->assertStringContainsString('бот с изкуствен интелект', $instructions);
    }

    public function test_instructions_contain_base_prompt(): void
    {
        $agent = new ForensicFireExpert;

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Ти си квалифициран експерт', $instructions);
        $this->assertStringContainsString('Фаза 1: Събиране на информация', $instructions);
    }
}
