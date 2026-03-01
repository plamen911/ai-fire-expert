<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AgentConversationTest extends TestCase
{
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
}
