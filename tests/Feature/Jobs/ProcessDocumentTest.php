<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcessDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
    }

    public function test_document_status_transitions_to_completed(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'uploaded_by' => $user->id,
            'status' => DocumentStatus::Pending,
        ]);

        $processor = Mockery::mock(DocumentProcessor::class);
        $processor->shouldReceive('process')
            ->once()
            ->with(Mockery::on(fn ($doc) => $doc->id === $document->id))
            ->andReturnUsing(function (Document $doc): void {
                $doc->update(['status' => DocumentStatus::Completed]);
            });

        $job = new ProcessDocument($document);
        $job->handle($processor);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Completed, $document->status);
    }

    public function test_document_status_set_to_failed_on_exception(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'uploaded_by' => $user->id,
            'status' => DocumentStatus::Pending,
        ]);

        $job = new ProcessDocument($document);
        $job->failed(new \RuntimeException('Test error'));

        $document->refresh();
        $this->assertEquals(DocumentStatus::Failed, $document->status);
        $this->assertEquals('Test error', $document->error_message);
    }

    public function test_chunks_created_with_mocked_processor(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'uploaded_by' => $user->id,
            'status' => DocumentStatus::Pending,
        ]);

        $fakeEmbedding = array_fill(0, 1536, 0.1);

        $processor = Mockery::mock(DocumentProcessor::class);
        $processor->shouldReceive('process')
            ->once()
            ->andReturnUsing(function (Document $doc) use ($fakeEmbedding): void {
                $doc->update(['status' => DocumentStatus::Processing]);

                $doc->chunks()->create([
                    'chunk_index' => 0,
                    'content' => 'Test chunk content',
                    'embedding' => $fakeEmbedding,
                ]);

                $doc->update(['status' => DocumentStatus::Completed]);
            });

        $job = new ProcessDocument($document);
        $job->handle($processor);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Completed, $document->status);
        $this->assertCount(1, $document->chunks);
    }
}
