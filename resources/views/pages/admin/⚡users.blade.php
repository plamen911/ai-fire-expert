<?php

use App\Actions\Fortify\CreateNewUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $position = '';

    public string $password = '';

    public string $password_confirmation = '';

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

    public function createUser(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $this->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        $user = (new CreateNewUser)->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        if ($this->position) {
            $user->update(['position' => $this->position]);
        }

        $this->resetCreateForm();
        $this->modal('create-user')->close();
    }

    public function deleteUser(int $userId): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        if ($userId === Auth::id()) {
            $this->addError('delete', __('You cannot delete your own account.'));

            return;
        }

        User::findOrFail($userId)->delete();
    }

    public function with(): array
    {
        return [
            'users' => User::with('roles')->orderBy('name')->get(),
        ];
    }

    public function resetCreateForm(): void
    {
        $this->reset(['name', 'email', 'position', 'password', 'password_confirmation']);
        $this->resetErrorBag();
    }
}; ?>

<div class="mx-auto w-full space-y-6 p-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>

            <flux:modal.trigger name="create-user">
                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add user') }}</flux:button>
            </flux:modal.trigger>
        </div>

        @error('role')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror

        @error('delete')
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
                            <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
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
                                <td class="px-6 py-1">
                                    @if($user->id !== Auth::id())
                                        <flux:modal.trigger name="delete-user-{{ $user->id }}">
                                            <flux:button variant="danger" size="sm" icon="trash" tooltip="{{ __('Delete') }}" />
                                        </flux:modal.trigger>

                                        <flux:modal name="delete-user-{{ $user->id }}" class="min-w-88">
                                            <div class="space-y-6">
                                                <div>
                                                    <flux:heading size="lg">{{ __('Delete user?') }}</flux:heading>
                                                    <flux:text class="mt-2">
                                                        {{ __('You are about to delete :name.', ['name' => $user->name]) }}<br>
                                                        {{ __('This action cannot be reversed.') }}
                                                    </flux:text>
                                                </div>
                                                <div class="flex gap-2">
                                                    <flux:spacer />
                                                    <flux:modal.close>
                                                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                                    </flux:modal.close>
                                                    <flux:button variant="danger" wire:click="deleteUser({{ $user->id }})">{{ __('Delete') }}</flux:button>
                                                </div>
                                            </div>
                                        </flux:modal>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <flux:modal name="create-user" class="md:w-xl" @close="$wire.resetCreateForm()">
            <form wire:submit="createUser" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Add user') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Create a new user account.') }}</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Email') }}</flux:label>
                        <flux:input type="email" wire:model="email" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field class="col-span-2">
                        <flux:label>{{ __('Position') }}</flux:label>
                        <flux:input wire:model="position" />
                        <flux:error name="position" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <flux:input type="password" wire:model="password" />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Confirm password') }}</flux:label>
                        <flux:input type="password" wire:model="password_confirmation" />
                    </flux:field>
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
