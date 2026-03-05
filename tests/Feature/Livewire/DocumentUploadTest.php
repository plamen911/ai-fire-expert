<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    public function test_regular_users_get_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_successful_docx_upload_creates_document_and_dispatches_job(): void
    {
        Bus::fake([ProcessDocument::class]);
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('test-report.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertCount(1, $processingFiles);
        $this->assertEquals('test-report.docx', $processingFiles[0]['filename']);
        $this->assertEquals('pending', $processingFiles[0]['status']);

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'test-report.docx',
            'uploaded_by' => $admin->id,
            'status' => DocumentStatus::Pending->value,
        ]);

        Bus::assertDispatched(ProcessDocument::class);
    }

    public function test_duplicate_file_hash_is_rejected(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('report.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $hash = hash_file('sha256', $file->getRealPath());

        Document::factory()->create([
            'file_hash' => $hash,
            'uploaded_by' => $admin->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertCount(1, $processingFiles);
        $this->assertEquals('failed', $processingFiles[0]['status']);
        $this->assertEquals('Този документ вече съществува в базата знания.', $processingFiles[0]['error']);
    }

    public function test_duplicate_filename_is_rejected(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Document::factory()->create([
            'original_filename' => 'duplicate.docx',
            'uploaded_by' => $admin->id,
        ]);

        $file = UploadedFile::fake()->create('duplicate.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertEquals('failed', $processingFiles[0]['status']);
    }

    public function test_non_supported_file_is_rejected(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('report.csv', 100, 'text/csv');

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertHasErrors(['files.0']);
    }

    public function test_txt_upload_is_accepted(): void
    {
        Bus::fake([ProcessDocument::class]);
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'Текст за тестване на txt файл.');

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertCount(1, $processingFiles);
        $this->assertEquals('notes.txt', $processingFiles[0]['filename']);

        Bus::assertDispatched(ProcessDocument::class);
    }

    public function test_pdf_upload_is_accepted(): void
    {
        Bus::fake([ProcessDocument::class]);
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertCount(1, $processingFiles);
        $this->assertEquals('report.pdf', $processingFiles[0]['filename']);

        Bus::assertDispatched(ProcessDocument::class);
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('huge.docx', 25000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertHasErrors();
    }

    public function test_multiple_files_uploaded_successfully(): void
    {
        Bus::fake([ProcessDocument::class]);
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $files = [
            UploadedFile::fake()->createWithContent('file-a.docx', str_repeat('a', 1024)),
            UploadedFile::fake()->createWithContent('file-b.docx', str_repeat('b', 1024)),
            UploadedFile::fake()->createWithContent('file-c.docx', str_repeat('c', 1024)),
        ];

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', $files)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertCount(3, $processingFiles);

        foreach ($processingFiles as $entry) {
            $this->assertEquals('pending', $entry['status']);
            $this->assertNotNull($entry['document_id']);
        }

        $this->assertDatabaseCount('documents', 3);
        Bus::assertDispatchedTimes(ProcessDocument::class, 3);
    }

    public function test_mixed_valid_and_duplicate_files(): void
    {
        Bus::fake([ProcessDocument::class]);
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Document::factory()->create([
            'original_filename' => 'existing.docx',
            'uploaded_by' => $admin->id,
        ]);

        $files = [
            UploadedFile::fake()->create('new-file.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('existing.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', $files)
            ->call('save')
            ->assertSet('showProcessingModal', true);

        $processingFiles = $component->get('processingFiles');
        $this->assertCount(2, $processingFiles);

        $this->assertEquals('pending', $processingFiles[0]['status']);
        $this->assertNotNull($processingFiles[0]['document_id']);

        $this->assertEquals('failed', $processingFiles[1]['status']);
        $this->assertEquals('Този документ вече съществува в базата знания.', $processingFiles[1]['error']);

        $this->assertDatabaseHas('documents', ['original_filename' => 'new-file.docx']);
        Bus::assertDispatchedTimes(ProcessDocument::class, 1);
    }

    public function test_admin_can_delete_document(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $filePath = 'documents/test-file.docx';
        Storage::disk('private')->put($filePath, 'content');

        $document = Document::factory()->create([
            'file_path' => $filePath,
            'uploaded_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->call('confirmDelete', $document->id)
            ->assertSet('confirmingDeleteId', $document->id)
            ->call('deleteDocument', $document->id)
            ->assertSet('confirmingDeleteId', null);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('private')->assertMissing($filePath);
    }

    public function test_regular_user_cannot_delete_document(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $document = Document::factory()->create([
            'uploaded_by' => $admin->id,
        ]);

        Livewire::actingAs($user)
            ->test('pages::documents.index')
            ->assertForbidden();
    }

    public function test_deleting_document_removes_chunks(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $filePath = 'documents/chunked-file.docx';
        Storage::disk('private')->put($filePath, 'content');

        $document = Document::factory()->create([
            'file_path' => $filePath,
            'uploaded_by' => $admin->id,
        ]);

        DocumentChunk::factory()->count(3)->create([
            'document_id' => $document->id,
        ]);

        $this->assertDatabaseCount('document_chunks', 3);

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->call('confirmDelete', $document->id)
            ->call('deleteDocument', $document->id);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseCount('document_chunks', 0);
    }

    public function test_cancel_delete_resets_state(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $document = Document::factory()->create([
            'uploaded_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->call('confirmDelete', $document->id)
            ->assertSet('confirmingDeleteId', $document->id)
            ->call('cancelDelete')
            ->assertSet('confirmingDeleteId', null);

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_admin_can_preview_document_content(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $document = Document::factory()->create([
            'uploaded_by' => $admin->id,
        ]);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Първи чънк текст.',
        ]);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => 'Втори чънк текст.',
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->call('previewDocument', $document->id);

        $component->assertSet('previewingDocumentId', $document->id);
        $component->assertSet('previewFilename', $document->original_filename);

        $previewContent = $component->get('previewContent');
        $this->assertStringContainsString('Първи чънк текст.', $previewContent);
        $this->assertStringContainsString('Втори чънк текст.', $previewContent);
    }

    public function test_preview_shows_message_when_no_chunks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $document = Document::factory()->create([
            'uploaded_by' => $admin->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->call('previewDocument', $document->id);

        $previewContent = $component->get('previewContent');
        $this->assertNotEmpty($previewContent);
    }

    public function test_close_preview_resets_state(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $document = Document::factory()->create([
            'uploaded_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->call('previewDocument', $document->id)
            ->assertSet('previewingDocumentId', $document->id)
            ->call('closePreview')
            ->assertSet('previewingDocumentId', null)
            ->assertSet('previewContent', null);
    }
}
