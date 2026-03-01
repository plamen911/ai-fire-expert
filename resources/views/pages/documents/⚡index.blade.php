<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Knowledge base')]
class extends Component {
    use WithFileUploads;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var array<int, array{filename: string, status: string, error: ?string, document_id: ?int}> */
    public array $processingFiles = [];

    public bool $showProcessingModal = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $this->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:docx', 'max:' . max_upload_size_kb()],
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

    public function with(): array
    {
        $hasProcessing = Document::whereIn('status', [DocumentStatus::Pending, DocumentStatus::Processing])->exists();

        return [
            'documents' => Document::with('uploader')->latest()->get(),
            'hasProcessing' => $hasProcessing,
            'maxUploadLabel' => format_bytes(max_upload_size_kb() * 1024),
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

    {{-- Upload panel --}}
    <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="lg" class="mb-4">{{ __('Upload document') }}</flux:heading>

        <form wire:submit="save" class="flex items-end gap-4">
            <flux:field class="flex-1">
                <flux:label>{{ __('Files (.docx, max :max)', ['max' => $maxUploadLabel]) }}</flux:label>
                <input type="file" wire:model="files" accept=".docx" multiple
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-center text-zinc-500">
                            {{ __('No uploaded documents.') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
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
</div>
