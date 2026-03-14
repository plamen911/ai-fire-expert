<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('admin.users'))->assertRedirect(route('login'));
    }

    public function test_regular_users_get_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_admin_can_view_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk();
    }

    public function test_admin_can_change_user_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('changeRole', $user->id, 'admin')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('changeRole', $admin->id, 'user')
            ->assertHasErrors(['role']);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->set('name', 'New User')
            ->set('email', 'newuser@example.com')
            ->set('position', 'Tester')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('createUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'position' => 'Tester',
        ]);

        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue($newUser->hasRole('user'));
    }

    public function test_create_user_validates_required_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('createUser')
            ->assertHasErrors(['name', 'email', 'password']);
    }

    public function test_create_user_validates_unique_email(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $existing = User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->set('name', 'Another User')
            ->set('email', 'taken@example.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('createUser')
            ->assertHasErrors(['email']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.users');

        $this->actingAs($user);

        $component
            ->set('name', 'Hacker')
            ->set('email', 'hacker@example.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('createUser')
            ->assertForbidden();
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('deleteUser', $user->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('deleteUser', $admin->id)
            ->assertHasErrors(['delete']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();
        $target->assignRole('user');

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.users');

        $this->actingAs($user);

        $component
            ->call('deleteUser', $target->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
