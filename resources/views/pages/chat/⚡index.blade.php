<?php

use App\Ai\Agents\ForensicFireExpert;
use App\Enums\ConversationStatus;
use App\Jobs\ProcessDocument;
use App\Models\AgentConversation;
use App\Models\ConversationFeedback;
use App\Services\DocumentProcessor;
use App\Services\ReportParser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::chat')]
#[Title('Chat')]
class extends Component {
    public string $message = '';

    public array $chatMessages = [];

    public bool $isStreaming = false;

    public ?string $conversationId = null;

    public ?string $reportContent = null;

    public ?string $reportFilename = null;

    public bool $showHistoryModal = false;

    public array $recentConversations = [];

    public string $historySearch = '';

    /** @var array<int, bool|null> */
    public array $feedback = [];

    public function mount(?string $conversationId = null): void
    {
        if ($conversationId) {
            if (! AgentConversation::where('id', $conversationId)->where('user_id', Auth::id())->exists()) {
                abort(404);
            }

            $agent = ForensicFireExpert::make()
                ->continue($conversationId, Auth::user());

        } else {
            $agent = ForensicFireExpert::make()
                ->continueLastConversation(Auth::user());
        }

        $this->conversationId = $agent->currentConversation();
        foreach ($agent->messages() as $msg) {
            $this->chatMessages[] = [
                'role' => $msg->role->value ?? $msg->role,
                'content' => $msg->content ?? '',
            ];
        }

        $this->restoreReportFromMessages();
        $this->loadFeedback();
        $this->dispatch('scroll-to-bottom');
    }

    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $userMessage = $this->message;
        $this->message = '';

        $this->chatMessages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $this->isStreaming = true;

        $agent = ForensicFireExpert::make();

        if ($this->conversationId) {
            $agent = $agent->continue($this->conversationId, Auth::user());
        } else {
            $agent = $agent->forUser(Auth::user());
        }

        try {
            $response = $agent->stream($userMessage);

            $fullText = '';

            $response->each(function (mixed $event) use (&$fullText): void {
                if ($event instanceof TextDelta) {
                    $fullText .= $event->delta;
                    $this->stream(content: $event->delta, to: 'assistant-response');
                }
            });
        } catch (FailoverableException $e) {
            report($e);

            $fullText = null;

            try {
                $fullText = (string) $agent->prompt($userMessage, provider: Lab::OpenAI, model: 'gpt-4o-mini');
            } catch (\Throwable $inner) {
                report($inner);
            }

            if ($fullText === null) {
                $this->isStreaming = false;

                $this->chatMessages[] = [
                    'role' => 'assistant',
                    'content' => __('An error occurred while communicating with the AI service. Please try again.'),
                ];

                $this->dispatch('message-rendered');
                $this->dispatch('scroll-to-bottom');

                return;
            }
        } catch (\Throwable $e) {
            report($e);

            $this->isStreaming = false;

            $this->chatMessages[] = [
                'role' => 'assistant',
                'content' => __('An error occurred while communicating with the AI service. Please try again.'),
            ];

            $this->dispatch('message-rendered');
            $this->dispatch('scroll-to-bottom');

            return;
        }

        $this->conversationId = $agent->currentConversation();

        if (empty(trim($fullText))) {
            $fullText = __('The AI service did not return a text response. Please try again.');
        }

        $this->chatMessages[] = [
            'role' => 'assistant',
            'content' => $fullText,
        ];

        $this->isStreaming = false;
        $this->dispatch('message-rendered');
        $this->dispatch('scroll-to-bottom');

        $report = app(ReportParser::class)->parse($fullText);

        if ($report) {
            AgentConversation::find($this->conversationId)
                ?->update(['status' => ConversationStatus::Completed]);

            $this->reportContent = $report['content'];
            $this->reportFilename = $report['filename'];

            $lastIndex = array_key_last($this->chatMessages);
            $this->chatMessages[$lastIndex]['content'] = __('The report has been generated successfully.');
            $this->chatMessages[$lastIndex]['is_report'] = true;

            // Auto-save to knowledge base (async) — the system learns from every generated expertise
            $document = app(DocumentProcessor::class)->createFromMarkdown(
                $report['content'],
                $report['filename'],
                Auth::id()
            );

            if ($document) {
                ProcessDocument::dispatch($document);
            }
        }

        if ($this->conversationId) {
            $conversation = AgentConversation::find($this->conversationId);
            if ($conversation?->needsTitleGeneration()) {
                $conversation->generateTitle($userMessage, $fullText);
            }
        }

    }

    public function newChat(): void
    {
        $this->chatMessages = [];
        $this->conversationId = null;
        $this->reportContent = null;
        $this->reportFilename = null;
    }

    public function loadRecentConversations(): void
    {
        $this->historySearch = '';
        $this->searchRecentConversations();
        $this->showHistoryModal = true;
    }

    public function updatedHistorySearch(): void
    {
        $this->searchRecentConversations();
    }

    private function searchRecentConversations(): void
    {
        $this->recentConversations = AgentConversation::query()
            ->where('user_id', Auth::id())
            ->when($this->historySearch, fn ($q) => $q->where('title', 'like', '%' . $this->historySearch . '%'))
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at->diffForHumans(),
            ])
            ->toArray();
    }

    public function loadConversation(string $conversationId): void
    {
        if (! AgentConversation::where('id', $conversationId)->where('user_id', Auth::id())->exists()) {
            abort(403);
        }

        $this->chatMessages = [];
        $this->conversationId = null;
        $this->reportContent = null;
        $this->reportFilename = null;

        $agent = ForensicFireExpert::make()
            ->continue($conversationId, Auth::user());

        $this->conversationId = $agent->currentConversation();

        foreach ($agent->messages() as $msg) {
            $this->chatMessages[] = [
                'role' => $msg->role->value ?? $msg->role,
                'content' => $msg->content ?? '',
            ];
        }

        $this->restoreReportFromMessages();
        $this->loadFeedback();
        $this->showHistoryModal = false;
        $this->dispatch('scroll-to-bottom');
    }

    public function submitFeedback(int $messageIndex, bool $isPositive): void
    {
        if (! $this->conversationId) {
            return;
        }

        ConversationFeedback::updateOrCreate(
            [
                'conversation_id' => $this->conversationId,
                'message_index' => $messageIndex,
                'user_id' => Auth::id(),
            ],
            ['is_positive' => $isPositive]
        );

        $this->feedback[$messageIndex] = $isPositive;
    }

    public function exportConversation(): StreamedResponse
    {
        $lines = [];
        $lines[] = '# ' . __('Conversation Export');
        $lines[] = '';
        $lines[] = '**' . __('Date') . ':** ' . now()->format('d.m.Y H:i');

        if ($this->conversationId) {
            $conversation = AgentConversation::find($this->conversationId);
            if ($conversation?->title) {
                $lines[] = '**' . __('Topic') . ':** ' . $conversation->title;
            }
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        foreach ($this->chatMessages as $msg) {
            $role = $msg['role'] === 'user' ? __('User') : __('Expert');
            $lines[] = '### ' . $role;
            $lines[] = '';
            $lines[] = $msg['content'];
            $lines[] = '';
        }

        $markdown = implode("\n", $lines);
        $filename = 'conversation_' . ($this->conversationId ? mb_substr($this->conversationId, 0, 8) : 'export') . '.md';

        return response()->streamDownload(function () use ($markdown): void {
            echo $markdown;
        }, $filename, [
            'Content-Type' => 'text/markdown',
        ]);
    }

    public function downloadReport(): StreamedResponse
    {
        $filename = $this->reportFilename ?? 'Ekspertiza.md';

        return response()->streamDownload(function (): void {
            echo $this->reportContent;
        }, $filename, [
            'Content-Type' => 'text/markdown',
        ]);
    }

    public function downloadReportPdf(): StreamedResponse
    {
        $html = \Illuminate\Support\Str::markdown($this->reportContent ?? '');
        $filename = str_replace('.md', '.pdf', $this->reportFilename ?? 'Ekspertiza.pdf');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            '<html><head><meta charset="UTF-8"><style>body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }</style></head><body>' . $html . '</body></html>'
        );

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function loadFeedback(): void
    {
        $this->feedback = [];

        if (! $this->conversationId) {
            return;
        }

        $existing = ConversationFeedback::where('conversation_id', $this->conversationId)
            ->where('user_id', Auth::id())
            ->pluck('is_positive', 'message_index');

        foreach ($existing as $index => $isPositive) {
            $this->feedback[$index] = $isPositive;
        }
    }

    private function restoreReportFromMessages(): void
    {
        $parser = app(ReportParser::class);

        foreach ($this->chatMessages as $index => $msg) {
            if (($msg['role'] ?? '') !== 'assistant') {
                continue;
            }

            $report = $parser->parse($msg['content']);

            if ($report) {
                $this->reportContent = $report['content'];
                $this->reportFilename = $report['filename'];
                $this->chatMessages[$index]['content'] = __('The report has been generated successfully.');
                $this->chatMessages[$index]['is_report'] = true;
                break;
            }
        }
    }

}; ?>

<div class="flex h-full w-full" x-data="{ autoScroll: true, pendingMessage: '' }"
     x-on:message-rendered.window="pendingMessage = ''"
     @keydown.window="
        if (($event.ctrlKey || $event.metaKey) && $event.key === 'n') { $event.preventDefault(); $wire.newChat(); }
        if (($event.ctrlKey || $event.metaKey) && $event.key === 'h') { $event.preventDefault(); $wire.loadRecentConversations(); }
        if ($event.key === 'Escape') { $wire.set('showHistoryModal', false); }
     ">
    {{-- Chat panel --}}
    <div class="flex min-h-0 flex-1 flex-col p-6">
        {{-- Header --}}
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="xl">{{ __('Chat with expert') }}</flux:heading>
            <div class="flex gap-2">
                @if($reportContent)
                    <flux:button size="sm" wire:click="downloadReport" icon="arrow-down-tray">
                        {{ __('Download .md') }}
                    </flux:button>
                    <flux:button size="sm" wire:click="downloadReportPdf" icon="document">
                        {{ __('Download .pdf') }}
                    </flux:button>
                @endif
                @if(count($chatMessages) > 0)
                    <flux:button size="sm" wire:click="exportConversation" icon="document-arrow-down">
                        {{ __('Export') }}
                    </flux:button>
                @endif
                <flux:button size="sm" wire:click="loadRecentConversations" icon="clock" tooltip="Ctrl+H">
                    {{ __('History') }}
                </flux:button>
                <flux:button size="sm" wire:click="newChat" icon="plus" tooltip="Ctrl+N">
                    {{ __('New chat') }}
                </flux:button>
            </div>
        </div>

        {{-- Messages --}}
        <div class="mb-4 flex-1 overflow-y-auto rounded-xl border border-neutral-200 p-4 dark:border-neutral-700"
             x-ref="chatContainer"
             x-on:scroll-to-bottom.window="if (autoScroll) $nextTick(() => $refs.chatContainer.scrollTop = $refs.chatContainer.scrollHeight)"
             x-init="$nextTick(() => $refs.chatContainer.scrollTop = $refs.chatContainer.scrollHeight)">

            @if(empty($chatMessages))
                <div class="flex h-full items-center justify-center text-zinc-400"
                     x-show="!pendingMessage"
                     wire:loading.remove wire:target="sendMessage">
                    <div class="text-center">
                        <flux:icon name="chat-bubble-left-right" class="mx-auto mb-2 size-12"/>
                        <p>{{ __('Start a conversation with the fire investigation expert.') }}</p>
                        <p class="mt-1 text-sm">{{ __('Describe the case or ask a question.') }}</p>
                    </div>
                </div>
            @endif

            @foreach($chatMessages as $index => $msg)
                <div wire:key="msg-{{ $index }}"
                     class="mb-4 flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div
                        class="max-w-[80%] rounded-xl px-4 py-3 {{ $msg['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' }}">
                        @if(! empty($msg['is_report']) && $reportContent)
                            <div class="flex items-center gap-3">
                                <flux:icon name="document-check" class="size-8 text-green-600 dark:text-green-400"/>
                                <div class="flex-1">
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('The report has been generated successfully.') }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $reportFilename }}</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <flux:button size="sm" wire:click="downloadReport" icon="arrow-down-tray">
                                    {{ __('Download') }}
                                </flux:button>
                            </div>
                        @elseif($msg['role'] === 'assistant')
                            @php
                                $sources = [];
                                $displayContent = preg_replace_callback(
                                    '/\[Източник:\s*([^\]]+)\]/',
                                    function ($matches) use (&$sources) {
                                        $filename = trim($matches[1]);
                                        $sources[] = $filename;
                                        return '';
                                    },
                                    $msg['content']
                                );
                                $sources = array_unique($sources);
                            @endphp
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($displayContent) !!}
                            </div>
                            @if(count($sources))
                                <details class="mt-3 border-t border-zinc-200 pt-2 dark:border-zinc-600">
                                    <summary class="cursor-pointer text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                        {{ __('Sources') }} ({{ count($sources) }})
                                    </summary>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($sources as $source)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                <flux:icon name="document-text" class="size-3" />
                                                {{ $source }}
                                            </span>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @else
                            {!! nl2br(e($msg['content'])) !!}
                        @endif
                    </div>
                    @if($msg['role'] === 'assistant' && $conversationId)
                        <div class="mt-1 flex gap-1">
                            <button wire:click="submitFeedback({{ $index }}, true)"
                                    class="rounded p-1 transition {{ ($feedback[$index] ?? null) === true ? 'text-green-600 dark:text-green-400' : 'text-zinc-300 hover:text-green-500 dark:text-zinc-600 dark:hover:text-green-400' }}"
                                    title="{{ __('Helpful') }}">
                                <flux:icon name="hand-thumb-up" class="size-4" />
                            </button>
                            <button wire:click="submitFeedback({{ $index }}, false)"
                                    class="rounded p-1 transition {{ ($feedback[$index] ?? null) === false ? 'text-red-500 dark:text-red-400' : 'text-zinc-300 hover:text-red-500 dark:text-zinc-600 dark:hover:text-red-400' }}"
                                    title="{{ __('Not helpful') }}">
                                <flux:icon name="hand-thumb-down" class="size-4" />
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Optimistic user message (shown instantly before Livewire responds) --}}
            <template x-if="pendingMessage">
                <div class="mb-4 flex justify-end">
                    <div class="max-w-[80%] rounded-xl bg-blue-600 px-4 py-3 text-white"
                         x-html="pendingMessage.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')">
                    </div>
                </div>
            </template>

            {{-- Thinking indicator --}}
            <div wire:loading wire:target="sendMessage" class="mb-4 flex justify-start">
                <div class="max-w-[80%] rounded-xl bg-zinc-100 px-4 py-3 dark:bg-zinc-700">
                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon.loading class="size-4"/>
                        {{ __('Thinking...') }}
                    </div>
                </div>
            </div>

            {{-- Streaming response area --}}
            @if($isStreaming)
                <div class="mb-4 flex justify-start">
                    <div
                        class="max-w-[80%] rounded-xl bg-zinc-100 px-4 py-3 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100">
                        <div class="prose prose-sm dark:prose-invert max-w-none"
                             wire:stream="assistant-response">
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <form wire:submit="sendMessage" class="flex flex-col gap-2"
              x-on:submit="pendingMessage = $wire.message; $nextTick(() => { $el.querySelector('textarea').value = ''; $refs.chatContainer.scrollTop = $refs.chatContainer.scrollHeight })">
            <flux:textarea wire:model="message"
                           :placeholder="__('Write a message...')"
                           :disabled="$isStreaming"
                           autocomplete="off"
                           autofocus
                           rows="3"
                           resize="none"
                           x-init="$nextTick(() => $el.focus())"
                           onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); this.closest('form').requestSubmit(); }"/>
            <flux:button x-show="$wire.message.trim().length > 0" x-cloak type="submit" variant="primary"
                         icon="paper-airplane" class="w-full" wire:loading.remove wire:target="sendMessage">
                {{ __('Send') }}
            </flux:button>
        </form>

        {{-- History modal --}}
        <flux:modal wire:model="showHistoryModal" class="max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Recent conversations') }}</flux:heading>

                <flux:input wire:model.live.debounce.300ms="historySearch"
                            icon="magnifying-glass"
                            :placeholder="__('Search...')"
                            size="sm"
                            clearable />

                @if(empty($recentConversations))
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No conversations yet.') }}</p>
                @else
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($recentConversations as $conversation)
                            <button wire:click="loadConversation('{{ $conversation['id'] }}')"
                                    class="gap-3 flex w-full items-center justify-between px-2 py-3 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $conversationId === $conversation['id'] ? 'bg-zinc-100 dark:bg-zinc-700' : '' }}">
                                <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $conversation['title'] ?: __('Untitled') }}
                                </span>
                                <span class="ml-2 shrink-0 text-xs text-zinc-400">
                                    {{ $conversation['updated_at'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <a href="{{ route('chat.history') }}"
                       class="text-sm text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                        {{ __('View full history') }} &rarr;
                    </a>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
