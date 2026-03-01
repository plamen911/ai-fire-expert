<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
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

    public function test_non_docx_file_is_rejected(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        Livewire::actingAs($admin)
            ->test('pages::documents.index')
            ->set('files', [$file])
            ->call('save')
            ->assertHasErrors(['files.0']);
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
}
