<?php

use App\Ai\Agents\ForensicFireExpert;
use App\Enums\ConversationStatus;
use App\Jobs\ProcessDocument;
use App\Models\AgentConversation;
use App\Services\DocumentProcessor;
use App\Services\ReportParser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
                    $this->stream(to: 'assistant-response', content: $event->delta);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            $this->isStreaming = false;

            $this->chatMessages[] = [
                'role' => 'assistant',
                'content' => __('An error occurred while communicating with the AI service. Please try again.'),
            ];

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
        $this->recentConversations = AgentConversation::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at->diffForHumans(),
            ])
            ->toArray();

        $this->showHistoryModal = true;
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
        $this->showHistoryModal = false;
        $this->dispatch('scroll-to-bottom');
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

<div class="flex h-full w-full" x-data="{ autoScroll: true }">
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
                @endif
                <flux:button size="sm" wire:click="loadRecentConversations" icon="clock">
                    {{ __('History') }}
                </flux:button>
                <flux:button size="sm" wire:click="newChat" icon="plus">
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
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($msg['content']) !!}
                            </div>
                        @else
                            {!! nl2br(e($msg['content'])) !!}
                        @endif
                    </div>
                </div>
            @endforeach

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
              x-on:submit="$nextTick(() => { $el.querySelector('textarea').value = ''; $refs.chatContainer.scrollTop = $refs.chatContainer.scrollHeight })">
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
