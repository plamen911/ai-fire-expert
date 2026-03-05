<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);
    }

    public function changeRole(int $userId, string $role): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        if ($userId === Auth::id()) {
            $this->addError('role', __('You cannot change your own role.'));

            return;
        }

        $user = User::findOrFail($userId);
        $user->syncRoles([$role]);
    }

    public function with(): array
    {
        return [
            'users' => User::with('roles')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full space-y-6 p-6">
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>

        @error('role')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror

        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</th>
                            <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Role') }}</th>
                            <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Registration') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td class="px-6 py-1 text-zinc-900 dark:text-zinc-100">{{ $user->name }}</td>
                                <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ $user->email }}</td>
                                <td class="px-6 py-1">
                                    @if($user->id === Auth::id())
                                        <flux:badge color="purple" size="sm">{{ $user->roles->first()?->name ?? 'user' }}</flux:badge>
                                    @else
                                        <flux:select wire:change="changeRole({{ $user->id }}, $event.target.value)" size="sm" class="w-28">
                                            <option value="user" @selected($user->hasRole('user'))>user</option>
                                            <option value="admin" @selected($user->hasRole('admin'))>admin</option>
                                        </flux:select>
                                    @endif
                                </td>
                                <td class="px-6 py-1 text-zinc-600 dark:text-zinc-400">{{ $user->created_at->format('d.m.Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
</div>
