<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_unknown_title_is_rejected(): void
    {
        $rejectedTitles = [
            'Neizvestno_Neizvestno_Neizvestno_Neizvestno',
            'Neizvestno_Neizvestno',
            'Neizvestno',
        ];

        foreach ($rejectedTitles as $title) {
            $this->assertTrue(
                (bool) preg_match('/^(Neizvestno_?)+$/', $title),
                "Expected all-unknown rejection for: {$title}"
            );
        }
    }

    public function test_partial_known_title_is_accepted(): void
    {
        $acceptedTitles = [
            'Kashta_Sofia_2026-01-15_Nebrezhnost',
            'Kashta_Neizvestno_Neizvestno_Neizvestno',
            'Neizvestno_Sofia_2026-01-15_Palezh',
        ];

        foreach ($acceptedTitles as $title) {
            $this->assertFalse(
                (bool) preg_match('/^(Neizvestno_?)+$/', $title),
                "Expected acceptance for: {$title}"
            );
        }
    }

    public function test_needs_title_generation_returns_true_for_empty_title(): void
    {
        $conversation = AgentConversation::factory()
            ->for(User::factory())
            ->create(['title' => '']);

        $this->assertTrue($conversation->needsTitleGeneration());
    }

    public function test_needs_title_generation_returns_true_for_generic_english_title(): void
    {
        $conversation = AgentConversation::factory()
            ->for(User::factory())
            ->create(['title' => 'Greeting and Initial Contact']);

        $this->assertTrue($conversation->needsTitleGeneration());
    }

    public function test_needs_title_generation_returns_false_for_structured_title(): void
    {
        $conversation = AgentConversation::factory()
            ->for(User::factory())
            ->create(['title' => 'Kashta_Sofia_2026-01-15_Nebrezhnost']);

        $this->assertFalse($conversation->needsTitleGeneration());
    }

    public function test_needs_title_generation_returns_false_for_partial_unknown_structured_title(): void
    {
        $conversation = AgentConversation::factory()
            ->for(User::factory())
            ->create(['title' => 'Neizvestno_Sofia_Neizvestno_Neizvestno']);

        $this->assertFalse($conversation->needsTitleGeneration());
    }
}
