<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Ai\Agents\ForensicFireExpert;
use App\Models\AgentConversation;
use App\Models\ConversationFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_user_can_submit_positive_feedback(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id])
            ->call('submitFeedback', 1, true);

        $this->assertDatabaseHas('conversation_feedback', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_index' => 1,
            'is_positive' => true,
        ]);

        $feedback = $component->get('feedback');
        $this->assertTrue($feedback[1]);
    }

    public function test_user_can_submit_negative_feedback(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id])
            ->call('submitFeedback', 3, false);

        $this->assertDatabaseHas('conversation_feedback', [
            'conversation_id' => $conversation->id,
            'message_index' => 3,
            'is_positive' => false,
        ]);
    }

    public function test_feedback_can_be_changed(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id])
            ->call('submitFeedback', 1, true)
            ->call('submitFeedback', 1, false);

        $this->assertDatabaseCount('conversation_feedback', 1);

        $this->assertDatabaseHas('conversation_feedback', [
            'conversation_id' => $conversation->id,
            'message_index' => 1,
            'is_positive' => false,
        ]);

        $feedback = $component->get('feedback');
        $this->assertFalse($feedback[1]);
    }

    public function test_feedback_is_loaded_on_mount(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create();

        ConversationFeedback::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_index' => 2,
            'is_positive' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id]);

        $feedback = $component->get('feedback');
        $this->assertTrue($feedback[2]);
    }

    public function test_feedback_without_conversation_is_ignored(): void
    {
        ForensicFireExpert::fake([]);

        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('conversationId', null)
            ->call('submitFeedback', 1, true);

        $this->assertDatabaseCount('conversation_feedback', 0);
    }
}
