<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\ConversationFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_shows_user_stats(): void
    {
        $user = User::factory()->create();
        AgentConversation::factory()->count(3)->create(['user_id' => $user->id]);
        AgentConversation::factory()->create(['user_id' => $user->id, 'is_starred' => true]);
        ConversationFeedback::factory()->create(['user_id' => $user->id, 'is_positive' => true]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSeeInOrder([__('My conversations'), '4']);
        $response->assertSeeInOrder([__('Starred'), '1']);
    }

    public function test_dashboard_shows_admin_stats(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee(__('System overview'));
        $response->assertSee(__('Users'));
        $response->assertSee(__('Documents'));
        $response->assertSee(__('All conversations'));
    }

    public function test_dashboard_hides_admin_stats_for_regular_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee(__('System overview'));
        $response->assertDontSee(__('All conversations'));
    }
}
