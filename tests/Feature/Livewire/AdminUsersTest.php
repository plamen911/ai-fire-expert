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
}
