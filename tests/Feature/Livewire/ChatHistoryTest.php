<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Ai\Agents\ForensicFireExpert;
use App\Enums\ConversationStatus;
use App\Models\AgentConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
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

    public function test_history_page_filters_conversations_by_search(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад Плевен']);
        AgentConversation::factory()->for($user)->create(['title' => 'Експертиза за къща София']);
        AgentConversation::factory()->for($user)->create(['title' => 'Палеж на автомобил']);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->set('search', 'Плевен')
            ->assertSee('Пожар в склад Плевен')
            ->assertDontSee('Експертиза за къща София')
            ->assertDontSee('Палеж на автомобил');
    }

    public function test_history_search_matches_partial_title(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад Варна']);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->set('search', 'склад')
            ->assertSee('Пожар в склад Варна');
    }

    public function test_empty_search_shows_all_conversations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->count(3)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->set('search', 'несъществуващо')
            ->set('search', '');

        $this->assertCount(3, $component->viewData('conversations'));
    }

    public function test_modal_search_filters_recent_conversations(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create(['title' => 'Пожар Плевен']);
        AgentConversation::factory()->for($user)->create(['title' => 'Палеж София']);

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->call('loadRecentConversations');

        $this->assertCount(2, $component->get('recentConversations'));

        $component->updateProperty('historySearch', 'Плевен');

        $recentConversations = $component->get('recentConversations');
        $this->assertCount(1, $recentConversations);
        $this->assertEquals('Пожар Плевен', $recentConversations[0]['title']);
    }

    public function test_export_conversation_from_history(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Тест експорт']);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->call('exportConversation', $conversation->id)
            ->assertFileDownloaded('conversation_' . mb_substr($conversation->id, 0, 8) . '.md');
    }

    public function test_toggle_star_marks_conversation_as_starred(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['is_starred' => false]);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->call('toggleStar', $conversation->id);

        $this->assertTrue($conversation->fresh()->is_starred);
    }

    public function test_toggle_star_unmarks_starred_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['is_starred' => true]);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->call('toggleStar', $conversation->id);

        $this->assertFalse($conversation->fresh()->is_starred);
    }

    public function test_starred_filter_shows_only_starred_conversations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        AgentConversation::factory()->for($user)->create(['title' => 'Starred one', 'is_starred' => true]);
        AgentConversation::factory()->for($user)->create(['title' => 'Not starred', 'is_starred' => false]);

        $component = Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->set('starredOnly', true);

        $conversations = $component->viewData('conversations');
        $this->assertCount(1, $conversations);
        $this->assertEquals('Starred one', $conversations->first()->title);
    }

    public function test_toggle_star_rejects_other_users_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $conversation = AgentConversation::factory()->for($otherUser)->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->call('toggleStar', $conversation->id);
    }

    public function test_export_conversation_rejects_other_users(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $conversation = AgentConversation::factory()->for($otherUser)->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->call('exportConversation', $conversation->id);
    }

    public function test_export_from_chat_page(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id])
            ->call('exportConversation')
            ->assertFileDownloaded();
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
