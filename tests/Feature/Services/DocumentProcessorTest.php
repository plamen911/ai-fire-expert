<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new DocumentProcessor;
    }

    public function test_extract_text_from_docx(): void
    {
        $filePath = $this->createTestDocx('Тестов текст за пожаро-техническа експертиза.');

        $text = $this->processor->extractText($filePath);

        $this->assertStringContainsString('Тестов текст', $text);
        $this->assertStringContainsString('пожаро-техническа', $text);

        unlink($filePath);
    }

    public function test_chunking_produces_correct_sizes(): void
    {
        $text = str_repeat('Това е тестово изречение. ', 200);

        $chunks = $this->processor->chunk($text);

        $this->assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(3000, mb_strlen($chunk), 'Chunk exceeds max size');
        }
    }

    public function test_chunking_with_overlap(): void
    {
        $sentences = [];
        for ($i = 0; $i < 100; $i++) {
            $sentences[] = "Изречение номер {$i} от тестовия документ за пожар.";
        }
        $text = implode(' ', $sentences);

        $chunks = $this->processor->chunk($text);

        if (count($chunks) > 1) {
            $lastWordsOfFirst = mb_substr($chunks[0], -100);
            $firstWordsOfSecond = mb_substr($chunks[1], 0, 500);

            $words = explode(' ', $lastWordsOfFirst);
            $overlapFound = false;
            foreach ($words as $word) {
                if (mb_strlen($word) > 3 && str_contains($firstWordsOfSecond, $word)) {
                    $overlapFound = true;
                    break;
                }
            }

            $this->assertTrue($overlapFound, 'Chunks should have overlapping content');
        }
    }

    public function test_empty_text_returns_empty_chunks(): void
    {
        $chunks = $this->processor->chunk('');

        $this->assertEmpty($chunks);
    }

    public function test_whitespace_only_text_returns_empty_chunks(): void
    {
        $chunks = $this->processor->chunk('   ');

        $this->assertEmpty($chunks);
    }

    public function test_create_from_markdown_creates_document_and_saves_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $markdown = "# Експертиза\n\nТестово съдържание за пожаро-техническа експертиза.";
        $filename = 'Ekspertiza_Test_2026-01-20.md';

        $document = $this->processor->createFromMarkdown($markdown, $filename, $user->id);

        $this->assertNotNull($document);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'original_filename' => $filename,
            'file_path' => 'generated/'.$filename,
            'status' => DocumentStatus::Pending->value,
            'uploaded_by' => $user->id,
        ]);

        Storage::disk('local')->assertExists('generated/'.$filename);
        $this->assertEquals($markdown, Storage::disk('local')->get('generated/'.$filename));
    }

    public function test_create_from_markdown_deduplicates_by_file_hash(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $markdown = "# Дублирана експертиза\n\nСъдържание.";
        $filename = 'Ekspertiza_Duplicate.md';

        $first = $this->processor->createFromMarkdown($markdown, $filename, $user->id);
        $second = $this->processor->createFromMarkdown($markdown, $filename, $user->id);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_process_handles_markdown_files(): void
    {
        $markdown = "# Тест експертиза\n\nТова е тестово съдържание. Достатъчно дълго изречение за чънкване.";
        $filename = 'Ekspertiza_Process_Test.md';

        $fullPath = storage_path('app/private/generated/'.$filename);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $markdown);

        $user = User::factory()->create();
        $document = Document::factory()->create([
            'file_path' => 'generated/'.$filename,
            'uploaded_by' => $user->id,
        ]);

        // Mock generateEmbeddings to avoid API calls
        $processor = $this->createPartialMock(DocumentProcessor::class, ['generateEmbeddings']);
        $processor->method('generateEmbeddings')
            ->willReturn([array_fill(0, 1536, 0.1)]);

        $processor->process($document);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Completed, $document->status);
        $this->assertGreaterThan(0, $document->chunks()->count());

        @unlink($fullPath);
    }

    public function test_extract_text_from_pdf_calls_parser(): void
    {
        $processor = $this->createPartialMock(DocumentProcessor::class, ['extractTextFromPdf']);
        $processor->method('extractTextFromPdf')
            ->willReturn('Тестов PDF текст за експертиза.');

        $text = $processor->extractTextFromPdf('/tmp/test.pdf');

        $this->assertStringContainsString('Тестов PDF текст', $text);
    }

    public function test_process_routes_pdf_to_extract_text_from_pdf(): void
    {
        $filename = 'test_document.pdf';
        $fullPath = storage_path('app/private/documents/' . $filename);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, 'dummy pdf content');

        $user = User::factory()->create();
        $document = Document::factory()->create([
            'file_path' => 'documents/' . $filename,
            'uploaded_by' => $user->id,
        ]);

        $processor = $this->createPartialMock(DocumentProcessor::class, ['extractTextFromPdf', 'generateEmbeddings']);
        $processor->method('extractTextFromPdf')
            ->willReturn('Извлечен текст от PDF файл за пожаро-техническа експертиза.');
        $processor->method('generateEmbeddings')
            ->willReturn([array_fill(0, 1536, 0.1)]);

        $processor->process($document);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Completed, $document->status);
        $this->assertGreaterThan(0, $document->chunks()->count());

        @unlink($fullPath);
    }

    public function test_process_routes_txt_to_file_get_contents(): void
    {
        $filename = 'test_notes.txt';
        $fullPath = storage_path('app/private/documents/' . $filename);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, 'Текстов файл с бележки за пожар в складова база.');

        $user = User::factory()->create();
        $document = Document::factory()->create([
            'file_path' => 'documents/' . $filename,
            'uploaded_by' => $user->id,
        ]);

        $processor = $this->createPartialMock(DocumentProcessor::class, ['generateEmbeddings']);
        $processor->method('generateEmbeddings')
            ->willReturn([array_fill(0, 1536, 0.1)]);

        $processor->process($document);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Completed, $document->status);
        $this->assertGreaterThan(0, $document->chunks()->count());

        $chunk = $document->chunks()->first();
        $this->assertStringContainsString('складова база', $chunk->content);

        @unlink($fullPath);
    }

    private function createTestDocx(string $content): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText($content);

        $filePath = tempnam(sys_get_temp_dir(), 'test_docx_').'.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);

        return $filePath;
    }

}
