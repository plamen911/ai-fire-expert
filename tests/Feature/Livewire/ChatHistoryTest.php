<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Ai\Agents\ForensicFireExpert;
use App\Enums\ConversationStatus;
use App\Models\AgentConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
    }

    public function test_guests_are_redirected_from_history_page(): void
    {
        $this->get(route('chat.history'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_history_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('chat.history'))
            ->assertOk();
    }

    public function test_history_page_lists_user_conversations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад']);
        AgentConversation::factory()->for($user)->create(['title' => 'Експертиза за къща']);

        $this->actingAs($user)
            ->get(route('chat.history'))
            ->assertSee('Пожар в склад')
            ->assertSee('Експертиза за къща');
    }

    public function test_history_page_does_not_show_other_users_conversations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        AgentConversation::factory()->for($otherUser)->create(['title' => 'Чужд разговор']);

        $this->actingAs($user)
            ->get(route('chat.history'))
            ->assertDontSee('Чужд разговор');
    }

    public function test_history_page_shows_completed_status_badge(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create([
            'title' => 'Завършен разговор',
            'status' => ConversationStatus::Completed,
        ]);

        $this->actingAs($user)
            ->get(route('chat.history'))
            ->assertSee('Завършен разговор')
            ->assertSeeText(__('Completed'));
    }

    public function test_history_page_shows_pending_status_badge(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create([
            'title' => 'Незавършен разговор',
            'status' => ConversationStatus::Pending,
        ]);

        $this->actingAs($user)
            ->get(route('chat.history'))
            ->assertSee('Незавършен разговор')
            ->assertSeeText(__('Pending'));
    }

    public function test_history_page_shows_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('chat.history'))
            ->assertSee('Няма разговори в историята.');
    }

    public function test_chat_page_loads_specific_conversation_by_url(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Конкретен разговор']);

        $this->actingAs($user)
            ->get(route('chat.index', $conversation->id))
            ->assertOk();
    }

    public function test_chat_page_returns_404_for_other_users_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $conversation = AgentConversation::factory()->for($otherUser)->create(['title' => 'Чужд разговор']);

        $this->actingAs($user)
            ->get(route('chat.index', $conversation->id))
            ->assertNotFound();
    }

    public function test_new_chat_clears_state(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('conversationId', 'some-id')
            ->set('chatMessages', [['role' => 'user', 'content' => 'test']])
            ->call('newChat')
            ->assertSet('conversationId', null)
            ->assertSet('chatMessages', []);
    }

    public function test_load_recent_conversations_populates_modal(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->count(3)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->call('loadRecentConversations');

        $component->assertSet('showHistoryModal', true);

        $recentConversations = $component->get('recentConversations');
        $this->assertCount(3, $recentConversations);
    }

    public function test_load_conversation_from_modal_switches_context(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Целеви разговор']);

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->call('loadConversation', $conversation->id);

        $component->assertSet('conversationId', $conversation->id);
        $component->assertSet('showHistoryModal', false);
    }

    public function test_load_conversation_rejects_other_users(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $conversation = AgentConversation::factory()->for($otherUser)->create(['title' => 'Чужд разговор']);

        Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->call('loadConversation', $conversation->id)
            ->assertForbidden();
    }
}
