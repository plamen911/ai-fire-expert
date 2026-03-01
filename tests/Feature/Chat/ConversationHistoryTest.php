<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\AgentConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('chat.history'))->assertOk();
    }

    public function test_conversations_are_paginated(): void
    {
        $user = User::factory()->create();

        AgentConversation::factory()->count(30)->for($user)->create();

        $this->actingAs($user);

        Livewire::test('pages::chat.history')
            ->assertSee('Next')
            ->assertViewHas('conversations', fn ($conversations) => $conversations->count() === 25);
    }

    public function test_delete_conversation_removes_conversation_and_messages(): void
    {
        $user = User::factory()->create();
        $conversation = AgentConversation::factory()->for($user)->create();

        DB::table('agent_conversation_messages')->insert([
            'id' => fake()->uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => 'test',
            'role' => 'user',
            'content' => 'Hello',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test('pages::chat.history')
            ->call('deleteConversation', $conversation->id)
            ->assertSet('confirmingDeleteId', null)
            ->assertSet('successMessage', __('Conversation deleted successfully.'));

        $this->assertDatabaseMissing('agent_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('agent_conversation_messages', ['conversation_id' => $conversation->id]);
    }

    public function test_cannot_delete_another_users_conversation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AgentConversation::factory()->for($otherUser)->create();

        $this->actingAs($user);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test('pages::chat.history')
            ->call('deleteConversation', $conversation->id);

        $this->assertDatabaseHas('agent_conversations', ['id' => $conversation->id]);
    }

    public function test_confirm_delete_sets_confirming_id(): void
    {
        $user = User::factory()->create();
        $conversation = AgentConversation::factory()->for($user)->create();

        $this->actingAs($user);

        Livewire::test('pages::chat.history')
            ->call('confirmDelete', $conversation->id)
            ->assertSet('confirmingDeleteId', $conversation->id);
    }

    public function test_cancel_delete_clears_confirming_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::chat.history')
            ->set('confirmingDeleteId', 'some-id')
            ->call('cancelDelete')
            ->assertSet('confirmingDeleteId', null);
    }
}
