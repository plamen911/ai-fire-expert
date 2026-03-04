<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\DocumentChunk;
use App\Services\DocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChunkBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new DocumentProcessor;
    }

    public function test_single_sentence_produces_one_chunk(): void
    {
        $text = 'Това е едно кратко изречение.';

        $chunks = $this->processor->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]);
    }

    public function test_text_exactly_at_chunk_size_produces_one_chunk(): void
    {
        // Build text of exactly 2400 chars using full sentences
        $sentence = 'Тестово изречение за чънкване на текст. ';
        $repeatCount = (int) floor(2400 / mb_strlen($sentence));
        $text = trim(str_repeat($sentence, $repeatCount));

        // Ensure it fits within the chunk size
        while (mb_strlen($text) > 2400) {
            $text = mb_substr($text, 0, mb_strrpos($text, '.') + 1);
        }

        $chunks = $this->processor->chunk($text);

        $this->assertCount(1, $chunks);
    }

    public function test_text_slightly_over_chunk_size_produces_two_chunks(): void
    {
        // Build text that clearly exceeds 2400 chars
        $sentence = 'Това е тестово изречение с достатъчна дължина за чънкване. ';
        $text = trim(str_repeat($sentence, 60));

        $this->assertGreaterThan(2400, mb_strlen($text));

        $chunks = $this->processor->chunk($text);

        $this->assertGreaterThanOrEqual(2, count($chunks));
    }

    public function test_overlap_preserves_context_between_chunks(): void
    {
        // Build a long text with distinct numbered sentences
        $sentences = [];
        for ($i = 1; $i <= 80; $i++) {
            $sentences[] = "Изречение номер {$i} от документа за пожар в сграда.";
        }
        $text = implode(' ', $sentences);

        $chunks = $this->processor->chunk($text);

        $this->assertGreaterThanOrEqual(2, count($chunks));

        // Verify overlap: last words of chunk N should appear at start of chunk N+1
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            $endOfCurrent = mb_substr($chunks[$i], -200);
            $startOfNext = mb_substr($chunks[$i + 1], 0, 500);

            // Extract a word from the end of current chunk and check if it appears in next
            $words = array_filter(explode(' ', $endOfCurrent), fn ($w) => mb_strlen($w) > 3);
            $overlapFound = false;
            foreach (array_slice($words, -5) as $word) {
                if (str_contains($startOfNext, $word)) {
                    $overlapFound = true;
                    break;
                }
            }

            $this->assertTrue($overlapFound, "Chunks {$i} and " . ($i + 1) . " should have overlap");
        }
    }

    public function test_single_very_long_sentence_is_not_lost(): void
    {
        // One sentence longer than chunk size (no sentence boundaries to split on)
        $longSentence = str_repeat('Дълъг текст без точка ', 200) . '.';

        $chunks = $this->processor->chunk($longSentence);

        $this->assertNotEmpty($chunks);

        // All content should be preserved across chunks
        $reconstructed = implode(' ', $chunks);
        $this->assertStringContainsString('Дълъг текст без точка', $reconstructed);
    }

    public function test_unicode_bulgarian_text_chunks_correctly(): void
    {
        $sentences = [];
        for ($i = 0; $i < 100; $i++) {
            $sentences[] = "Пожарът в жилищната сграда е причинен от късо съединение в електрическата инсталация на втория етаж.";
        }
        $text = implode(' ', $sentences);

        $chunks = $this->processor->chunk($text);

        $this->assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            // No chunk should exceed reasonable size
            $this->assertLessThanOrEqual(3000, mb_strlen($chunk));
            // Each chunk should contain valid Bulgarian text
            $this->assertMatchesRegularExpression('/[а-яА-Я]/u', $chunk);
        }
    }

    public function test_text_with_only_periods_chunks_correctly(): void
    {
        $text = str_repeat('Изречение. ', 100);

        $chunks = $this->processor->chunk(trim($text));

        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk));
        }
    }

    public function test_no_content_is_lost_during_chunking(): void
    {
        $sentences = [];
        for ($i = 1; $i <= 50; $i++) {
            $sentences[] = "Уникално изречение МАРКЕР_{$i} за проверка на пълнота.";
        }
        $text = implode(' ', $sentences);

        $chunks = $this->processor->chunk($text);
        $allContent = implode(' ', $chunks);

        for ($i = 1; $i <= 50; $i++) {
            $this->assertStringContainsString("МАРКЕР_{$i}", $allContent, "Marker {$i} should be present in chunks");
        }
    }

    public function test_keyword_search_scope_finds_matching_chunks(): void
    {
        $document = \App\Models\Document::factory()->create();

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Пожарът е причинен от късо съединение в електрическата инсталация.',
        ]);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Сградата е с метална конструкция и покрив от сандвич панели.',
        ]);

        $results = DocumentChunk::keywordSearch('късо съединение')->get();

        $this->assertCount(1, $results);
        $this->assertStringContainsString('късо съединение', $results->first()->content);
    }

    public function test_keyword_search_scope_returns_empty_for_no_match(): void
    {
        $document = \App\Models\Document::factory()->create();

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Сградата е с метална конструкция.',
        ]);

        $results = DocumentChunk::keywordSearch('несъществуващ термин')->get();

        $this->assertCount(0, $results);
    }
}
