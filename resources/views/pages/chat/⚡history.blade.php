<?php

use App\Models\AgentConversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Conversation history')] class extends Component {
    use WithPagination;

    public ?string $confirmingDeleteId = null;

    public ?string $successMessage = null;

    public function renameConversation(string $conversationId, string $newTitle): void
    {
        $newTitle = trim($newTitle);

        if ($newTitle === '') {
            return;
        }

        AgentConversation::where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->update(['title' => $newTitle]);
    }

    public function confirmDelete(string $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteConversation(string $id): void
    {
        $conversation = AgentConversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversation->id)
            ->delete();

        $conversation->delete();

        $this->confirmingDeleteId = null;
        $this->successMessage = __('Conversation deleted successfully.');
    }

    public function with(): array
    {
        return [
            'conversations' => AgentConversation::query()
                ->where('user_id', Auth::id())
                ->orderByDesc('updated_at')
                ->paginate(25),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Conversation history') }}</flux:heading>
        <flux:button :href="route('chat.index')" icon="plus" size="sm" wire:navigate>
            {{ __('New chat') }}
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle" :heading="$successMessage">
            <x-slot name="controls">
                <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="$set('successMessage', null)" />
            </x-slot>
        </flux:callout>
    @endif

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-neutral-200 dark:border-neutral-700">
                    <tr>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Topic') }}</th>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Last activity') }}</th>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($conversations as $conversation)
                        <tr wire:key="conv-{{ $conversation->id }}"
                            x-data="{ editing: false, newTitle: '{{ str_replace("'", "\\'", $conversation->title) }}' }">
                            <td class="group px-6 py-1 text-zinc-900 dark:text-zinc-100">
                                <div x-show="!editing" class="flex items-center gap-2">
                                    <span>{{ $conversation->title }}</span>
                                    <button @click.stop="editing = true; $nextTick(() => $refs.historyRename{{ str_replace('-', '', $conversation->id) }}.focus())"
                                            class="invisible rounded p-0.5 text-zinc-400 hover:text-zinc-600 group-hover:visible dark:hover:text-zinc-300">
                                        <flux:icon name="pencil-square" class="size-3.5" />
                                    </button>
                                </div>
                                <div x-show="editing" x-cloak class="flex items-center gap-2">
                                    <input type="text"
                                           x-model="newTitle"
                                           x-ref="historyRename{{ str_replace('-', '', $conversation->id) }}"
                                           @keydown.enter="if(newTitle.trim()) { $wire.renameConversation('{{ $conversation->id }}', newTitle); editing = false; }"
                                           @keydown.escape="editing = false; newTitle = '{{ str_replace("'", "\\'", $conversation->title) }}'"
                                           class="w-full rounded border border-zinc-300 bg-white px-2 py-0.5 text-sm focus:border-blue-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100" />
                                    <button @click="if(newTitle.trim()) { $wire.renameConversation('{{ $conversation->id }}', newTitle); editing = false; }"
                                            class="shrink-0 rounded p-0.5 text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                        <flux:icon name="check" class="size-4" />
                                    </button>
                                    <button @click="editing = false; newTitle = '{{ str_replace("'", "\\'", $conversation->title) }}'"
                                            class="shrink-0 rounded p-0.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                        <flux:icon name="x-mark" class="size-4" />
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-1">
                                <flux:badge size="sm"
                                    :color="$conversation->status === \App\Enums\ConversationStatus::Completed ? 'green' : 'zinc'"
                                >
                                    {{ $conversation->status === \App\Enums\ConversationStatus::Completed ? __('Completed') : __('Pending') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ \Carbon\Carbon::parse($conversation->updated_at)->diffForHumans() }}</td>
                            <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ \Carbon\Carbon::parse($conversation->created_at)->format('d.m.Y H:i') }}</td>
                            <td class="px-6 py-1 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button :href="route('chat.index', $conversation->id)" size="sm" icon="arrow-top-right-on-square" wire:navigate tooltip="{{ __('Open') }}" />
                                    <flux:button variant="danger" size="sm" icon="trash" wire:click="confirmDelete('{{ $conversation->id }}')" wire:target="confirmDelete('{{ $conversation->id }}')" tooltip="{{ __('Delete') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">
                                {{ __('No conversations in history.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($conversations->hasPages())
            <div class="px-6 py-3">
                {{ $conversations->links() }}
            </div>
        @endif
    </div>

    {{-- Delete confirmation modal --}}
    @if($confirmingDeleteId)
        @php
            $conversationToDelete = AgentConversation::find($confirmingDeleteId);
        @endphp

        <flux:modal wire:model.self="confirmingDeleteId" :closable="true">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Delete conversation') }}</flux:heading>

                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Are you sure you want to delete :title? This action cannot be undone.', ['title' => $conversationToDelete?->title]) }}
                </p>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="cancelDelete">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="deleteConversation('{{ $confirmingDeleteId }}')">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
