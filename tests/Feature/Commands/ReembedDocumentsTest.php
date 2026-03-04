<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReembedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_all_or_document_option(): void
    {
        $this->artisan('documents:reembed')
            ->expectsOutput('Please specify --all or --document={id}')
            ->assertExitCode(1);
    }

    public function test_warns_when_no_documents_found(): void
    {
        $this->artisan('documents:reembed', ['--all' => true])
            ->expectsOutput('No documents found to re-embed.')
            ->assertExitCode(0);
    }

    public function test_reembeds_specific_document(): void
    {
        $user = User::factory()->create();

        $markdown = "# Тест\n\nТестово съдържание за реиндексиране.";
        $filename = 'Reembed_Test.md';

        $fullPath = storage_path('app/private/generated/' . $filename);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $markdown);

        $document = Document::factory()->create([
            'file_path' => 'generated/' . $filename,
            'status' => DocumentStatus::Completed,
            'uploaded_by' => $user->id,
        ]);

        DocumentChunk::factory()->count(2)->create([
            'document_id' => $document->id,
        ]);

        $this->mock(DocumentProcessor::class, function ($mock) {
            $mock->shouldReceive('process')->once();
        });

        $this->artisan('documents:reembed', ['--document' => $document->id])
            ->assertExitCode(0);

        @unlink($fullPath);
    }

    public function test_reembeds_all_completed_documents(): void
    {
        $user = User::factory()->create();

        $documents = [];
        foreach (['file_a.md', 'file_b.md'] as $filename) {
            $fullPath = storage_path('app/private/generated/' . $filename);
            @mkdir(dirname($fullPath), 0755, true);
            file_put_contents($fullPath, '# Test content');

            $documents[] = Document::factory()->create([
                'file_path' => 'generated/' . $filename,
                'status' => DocumentStatus::Completed,
                'uploaded_by' => $user->id,
            ]);
        }

        // Create a failed document that should NOT be re-embedded
        Document::factory()->create([
            'status' => DocumentStatus::Failed,
            'uploaded_by' => $user->id,
        ]);

        $this->mock(DocumentProcessor::class, function ($mock) {
            $mock->shouldReceive('process')->twice();
        });

        $this->artisan('documents:reembed', ['--all' => true])
            ->assertExitCode(0);

        foreach ($documents as $document) {
            @unlink(storage_path('app/private/' . $document->file_path));
        }
    }

    public function test_deletes_old_chunks_before_reembedding(): void
    {
        $user = User::factory()->create();

        $filename = 'Rechunk_Test.md';
        $fullPath = storage_path('app/private/generated/' . $filename);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, '# Test');

        $document = Document::factory()->create([
            'file_path' => 'generated/' . $filename,
            'status' => DocumentStatus::Completed,
            'uploaded_by' => $user->id,
        ]);

        DocumentChunk::factory()->count(5)->create([
            'document_id' => $document->id,
        ]);

        $this->assertDatabaseCount('document_chunks', 5);

        $this->mock(DocumentProcessor::class, function ($mock) {
            $mock->shouldReceive('process')->once();
        });

        $this->artisan('documents:reembed', ['--document' => $document->id])
            ->assertExitCode(0);

        // Old chunks should be deleted (new ones created by mocked process)
        $this->assertDatabaseCount('document_chunks', 0);

        @unlink($fullPath);
    }
}
