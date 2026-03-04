<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Knowledge base')]
class extends Component {
    use WithFileUploads, WithPagination;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var array<int, array{filename: string, status: string, error: ?string, document_id: ?int}> */
    public array $processingFiles = [];

    public bool $showProcessingModal = false;

    public ?int $confirmingDeleteId = null;

    public ?string $successMessage = null;

    public ?int $previewingDocumentId = null;

    public ?string $previewContent = null;

    public ?string $previewFilename = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $this->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:docx,pdf,txt', 'max:' . max_upload_size_kb()],
        ]);

        $this->processingFiles = [];

        foreach ($this->files as $file) {
            $originalFilename = $file->getClientOriginalName();
            $hash = hash_file('sha256', $file->getRealPath());

            $exists = Document::where('file_hash', $hash)
                ->orWhere('original_filename', $originalFilename)
                ->exists();

            if ($exists) {
                $this->processingFiles[] = [
                    'filename' => $originalFilename,
                    'status' => 'failed',
                    'error' => __('This document already exists in the knowledge base.'),
                    'document_id' => null,
                ];

                continue;
            }

            $path = $file->store('documents', 'private');

            $document = Document::create([
                'original_filename' => $originalFilename,
                'file_hash' => $hash,
                'file_path' => $path,
                'status' => DocumentStatus::Pending,
                'uploaded_by' => Auth::id(),
            ]);

            ProcessDocument::dispatch($document);

            $this->processingFiles[] = [
                'filename' => $originalFilename,
                'status' => 'pending',
                'error' => null,
                'document_id' => $document->id,
            ];
        }

        $this->showProcessingModal = true;
        $this->reset('files');
    }

    public function checkProcessingStatus(): void
    {
        foreach ($this->processingFiles as $index => $entry) {
            if (!$entry['document_id']) {
                continue;
            }

            $document = Document::find($entry['document_id']);

            if (!$document) {
                continue;
            }

            $this->processingFiles[$index]['status'] = $document->status->value;

            if ($document->status === DocumentStatus::Failed) {
                $this->processingFiles[$index]['error'] = $document->error_message ?? __('An error occurred during processing.');
                $this->processingFiles[$index]['document_id'] = null;
            } elseif ($document->status === DocumentStatus::Completed) {
                $this->processingFiles[$index]['document_id'] = null;
            }
        }
    }

    public function closeProcessingModal(): void
    {
        $this->showProcessingModal = false;
        $this->processingFiles = [];
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteDocument(int $id): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $document = Document::findOrFail($id);

        Storage::disk('private')->delete($document->file_path);

        $document->delete();

        $this->confirmingDeleteId = null;
        $this->successMessage = __('Document deleted successfully.');
    }

    public function previewDocument(int $id): void
    {
        $document = Document::with('chunks')->findOrFail($id);

        $this->previewingDocumentId = $document->id;
        $this->previewFilename = $document->original_filename;
        $this->previewContent = $document->chunks
            ->sortBy('chunk_index')
            ->pluck('content')
            ->join("\n\n---\n\n");

        if (empty($this->previewContent)) {
            $this->previewContent = __('No extracted content available for this document.');
        }
    }

    public function closePreview(): void
    {
        $this->previewingDocumentId = null;
        $this->previewContent = null;
        $this->previewFilename = null;
    }

    public function with(): array
    {
        $hasProcessing = Document::whereIn('status', [DocumentStatus::Pending, DocumentStatus::Processing])->exists();
        $totalDocs = Document::count();
        $totalChunks = DocumentChunk::count();

        return [
            'documents' => Document::with('uploader')->latest()->paginate(25),
            'hasProcessing' => $hasProcessing,
            'maxUploadLabel' => format_bytes(max_upload_size_kb() * 1024),
            'totalDocs' => $totalDocs,
            'totalChunks' => $totalChunks,
            'avgChunks' => $totalDocs > 0 ? round($totalChunks / $totalDocs, 1) : 0,
            'completedDocs' => Document::where('status', DocumentStatus::Completed)->count(),
            'failedDocs' => Document::where('status', DocumentStatus::Failed)->count(),
        ];
    }

    private function hasStillProcessing(): bool
    {
        return collect($this->processingFiles)->contains(fn(array $entry): bool => in_array($entry['status'], ['pending', 'processing']));
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-6 p-6"
     @if($hasProcessing) wire:poll.3s @endif>

    <flux:heading size="xl">{{ __('Knowledge base') }}</flux:heading>

    {{-- Stats cards --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Documents') }}</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalDocs }}</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Chunks') }}</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalChunks }}</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Avg chunks/doc') }}</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $avgChunks }}</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</span>
            <span class="text-sm text-zinc-900 dark:text-zinc-100">
                <span class="font-semibold text-green-600 dark:text-green-400">{{ $completedDocs }}</span> {{ __('ok') }}
                @if($failedDocs > 0)
                    / <span class="font-semibold text-red-500">{{ $failedDocs }}</span> {{ __('err') }}
                @endif
            </span>
        </div>
    </div>

    {{-- Upload panel --}}
    <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="lg" class="mb-4">{{ __('Upload document') }}</flux:heading>

        <form wire:submit="save" class="flex items-end gap-4">
            <flux:field class="flex-1">
                <flux:label>{{ __('Files (.docx, .pdf, .txt, max :max)', ['max' => $maxUploadLabel]) }}</flux:label>
                <input type="file" wire:model="files" accept=".docx,.pdf,.txt" multiple
                       class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-700 dark:file:text-zinc-300"/>
                <flux:error name="files.*"/>
            </flux:field>

            <div class="shrink-0">
                <div wire:loading wire:target="files" class="pb-2 text-sm text-zinc-500">
                    {{ __('Loading files...') }}
                </div>

                @if(count($files))
                    <flux:button type="submit" variant="primary">
                        {{ __('Upload') }}
                    </flux:button>
                @endif
            </div>
        </form>
    </div>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle" :heading="$successMessage">
            <x-slot name="controls">
                <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="$set('successMessage', null)" />
            </x-slot>
        </flux:callout>
    @endif

    {{-- Document list --}}
    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-neutral-200 dark:border-neutral-700">
                <tr>
                    <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('File') }}</th>
                    <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Uploaded by') }}</th>
                    <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse($documents as $document)
                    <tr wire:key="doc-{{ $document->id }}">
                        <td class="px-6 py-1 text-zinc-900 dark:text-zinc-100">{{ $document->original_filename }}</td>
                        <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ $document->uploader?->name }}</td>
                        <td class="px-6 py-1">
                            @switch($document->status)
                                @case(DocumentStatus::Pending)
                                    <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                                    @break
                                @case(DocumentStatus::Processing)
                                    <flux:badge color="blue" size="sm">{{ __('Processing') }}</flux:badge>
                                    @break
                                @case(DocumentStatus::Completed)
                                    <flux:badge color="green" size="sm">{{ __('Completed') }}</flux:badge>
                                    @break
                                @case(DocumentStatus::Failed)
                                    <flux:badge color="red" size="sm" :title="$document->error_message">{{ __('Error') }}
                                    </flux:badge>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ $document->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-6 py-1 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($document->status === DocumentStatus::Completed)
                                    <flux:button size="sm" icon="eye" wire:click="previewDocument({{ $document->id }})" tooltip="{{ __('View') }}" />
                                @endif
                                <flux:button variant="danger" size="sm" icon="trash" wire:click="confirmDelete({{ $document->id }})" wire:target="confirmDelete({{ $document->id }})" tooltip="{{ __('Delete') }}" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-3 text-center text-zinc-500">
                            {{ __('No uploaded documents.') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
            <div class="px-6 py-3">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    {{-- Processing modal --}}
    @if($showProcessingModal)
        <div @if($this->hasStillProcessing()) wire:poll.2s="checkProcessingStatus" @endif>
            <flux:modal wire:model="showProcessingModal" :closable="false">
                <div class="flex flex-col items-center space-y-4 py-4">
                    <flux:heading size="lg">{{ __('Processing documents') }}</flux:heading>

                    <div class="max-h-[70vh] w-full space-y-3 overflow-y-auto">
                        @foreach($processingFiles as $entry)
                            <div
                                class="flex items-center gap-3 rounded-lg border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                                @if(in_array($entry['status'], ['pending', 'processing']))
                                    <svg class="h-5 w-5 shrink-0 animate-spin text-blue-500"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                @elseif($entry['status'] === 'completed')
                                    <div
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                        <flux:icon.check class="h-3.5 w-3.5 text-green-600 dark:text-green-400"/>
                                    </div>
                                @elseif($entry['status'] === 'failed')
                                    <div
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                        <flux:icon.x-mark class="h-3.5 w-3.5 text-red-600 dark:text-red-400"/>
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-zinc-900 dark:text-zinc-100">{{ $entry['filename'] }}</p>
                                    @if($entry['status'] === 'pending')
                                        <p class="text-xs text-zinc-400">{{ __('Awaiting processing...') }}</p>
                                    @elseif($entry['status'] === 'processing')
                                        <p class="text-xs text-blue-500">{{ __('Processing...') }}</p>
                                    @elseif($entry['status'] === 'completed')
                                        <p class="text-xs text-green-600 dark:text-green-400">{{ __('Successfully processed') }}</p>
                                    @elseif($entry['status'] === 'failed')
                                        <p class="text-xs text-red-500">{{ $entry['error'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>
            </flux:modal>
        </div>
    @endif

    {{-- Document preview modal --}}
    @if($previewingDocumentId)
        <flux:modal wire:model.self="previewingDocumentId" class="max-w-3xl" :closable="true">
            <div class="space-y-4">
                <flux:heading size="lg">{{ $previewFilename }}</flux:heading>

                <div class="max-h-[70vh] overflow-y-auto rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                    <div class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">
                        {!! Str::markdown($previewContent) !!}
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button wire:click="closePreview">{{ __('Close') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Delete confirmation modal --}}
    @if($confirmingDeleteId)
        @php
            $documentToDelete = Document::find($confirmingDeleteId);
        @endphp

        <flux:modal wire:model.self="confirmingDeleteId" :closable="true">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Delete document') }}</flux:heading>

                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Are you sure you want to delete :filename? This action cannot be undone.', ['filename' => $documentToDelete?->original_filename]) }}
                </p>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="cancelDelete">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="deleteDocument({{ $confirmingDeleteId }})">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
