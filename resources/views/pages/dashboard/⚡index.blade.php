<?php

use App\Enums\DocumentStatus;
use App\Models\AgentConversation;
use App\Models\ConversationFeedback;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')]
class extends Component {
    public function with(): array
    {
        $user = Auth::user();

        $data = [
            'myConversations' => AgentConversation::where('user_id', $user->id)->count(),
            'myStarred' => AgentConversation::where('user_id', $user->id)->where('is_starred', true)->count(),
            'myFeedbackPositive' => ConversationFeedback::where('user_id', $user->id)->where('is_positive', true)->count(),
            'myFeedbackNegative' => ConversationFeedback::where('user_id', $user->id)->where('is_positive', false)->count(),
            'recentConversations' => AgentConversation::where('user_id', $user->id)
                ->latest('updated_at')
                ->limit(10)
                ->get(),
            'isAdmin' => $user->isAdmin(),
        ];

        if ($user->isAdmin()) {
            $data['totalUsers'] = User::count();
            $data['totalDocuments'] = Document::count();
            $data['completedDocs'] = Document::where('status', DocumentStatus::Completed)->count();
            $data['failedDocs'] = Document::where('status', DocumentStatus::Failed)->count();
            $data['pendingDocs'] = Document::whereIn('status', [DocumentStatus::Pending, DocumentStatus::Processing])->count();
            $data['totalChunks'] = DocumentChunk::count();
            $data['totalConversations'] = AgentConversation::count();
            $data['totalFeedback'] = ConversationFeedback::count();
            $data['positiveFeedback'] = ConversationFeedback::where('is_positive', true)->count();
        }

        return $data;
    }
}; ?>

<div class="w-full space-y-6 p-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    {{-- User stats --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('My conversations') }}</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $myConversations }}</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Starred') }}</span>
            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $myStarred }}</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Feedback') }}</span>
            <span class="text-sm text-zinc-900 dark:text-zinc-100">
                <span class="font-semibold text-green-600 dark:text-green-400">{{ $myFeedbackPositive }}</span>
                /
                <span class="font-semibold text-red-500">{{ $myFeedbackNegative }}</span>
            </span>
        </div>
    </div>

    {{-- Admin stats --}}
    @if($isAdmin)
        <flux:heading size="lg">{{ __('System overview') }}</flux:heading>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Users') }}</span>
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalUsers }}</span>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Documents') }}</span>
                <span class="text-sm text-zinc-900 dark:text-zinc-100">
                    <span class="font-semibold">{{ $totalDocuments }}</span>
                    (<span class="text-green-600 dark:text-green-400">{{ $completedDocs }}</span>
                    / <span class="text-red-500">{{ $failedDocs }}</span>
                    / <span class="text-yellow-500">{{ $pendingDocs }}</span>)
                </span>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Chunks') }}</span>
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalChunks }}</span>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('All conversations') }}</span>
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalConversations }}</span>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 dark:border-neutral-700">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Feedback sentiment') }}</span>
                <span class="text-sm text-zinc-900 dark:text-zinc-100">
                    <span class="font-semibold text-green-600 dark:text-green-400">{{ $positiveFeedback }}</span>
                    / <span class="font-semibold">{{ $totalFeedback }}</span>
                </span>
            </div>
        </div>
    @endif

    {{-- Recent conversations --}}
    <flux:heading size="lg">{{ __('Recent conversations') }}</flux:heading>

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-neutral-200 dark:border-neutral-700">
                    <tr>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Topic') }}</th>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Last activity') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($recentConversations as $conversation)
                        <tr wire:key="conv-{{ $conversation->id }}">
                            <td class="px-6 py-1 text-zinc-900 dark:text-zinc-100">{{ $conversation->title }}</td>
                            <td class="px-6 py-1">
                                <flux:badge size="sm"
                                    :color="$conversation->status === \App\Enums\ConversationStatus::Completed ? 'green' : 'zinc'"
                                >
                                    {{ $conversation->status === \App\Enums\ConversationStatus::Completed ? __('Completed') : __('Pending') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ $conversation->updated_at->diffForHumans() }}</td>
                            <td class="px-6 py-1 text-right">
                                <flux:button :href="route('chat.index', $conversation->id)" size="sm" icon="arrow-top-right-on-square" wire:navigate tooltip="{{ __('Open') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-zinc-500">
                                {{ __('No conversations yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
