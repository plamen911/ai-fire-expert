<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Ai\Agents\ForensicFireExpert;
use App\Enums\ConversationStatus;
use App\Jobs\ProcessDocument;
use App\Models\AgentConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\RateLimitedException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('chat.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertOk();
    }

    public function test_message_sent_successfully(): void
    {
        ForensicFireExpert::fake([
            'Здравейте! Как мога да ви помогна с пожаро-техническата експертиза?',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Здравейте, имам случай на пожар.')
            ->call('sendMessage')
            ->assertHasNoErrors();
    }

    public function test_empty_message_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', '')
            ->call('sendMessage')
            ->assertHasErrors(['message']);
    }

    public function test_report_detected_in_ai_response(): void
    {
        $reportResponse = "Ето вашата експертиза:\n\n<!-- REPORT_START -->\n<!-- REPORT_FILENAME:Ekspertiza_Sklad_Sofia_2026-01-15_KasoSaedinenie.md -->\n# ЕКСПЕРТНО ПОЖАРО-ТЕХНИЧЕСКО ЗАКЛЮЧЕНИЕ\n\nТест съдържание\n<!-- REPORT_END -->";

        ForensicFireExpert::fake([$reportResponse]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Генерирай експертиза')
            ->call('sendMessage');

        $component->assertSet('reportFilename', 'Ekspertiza_Sklad_Sofia_2026-01-15_KasoSaedinenie.md');
        $component->assertNotSet('reportContent', null);
    }

    public function test_report_message_replaced_with_short_notice(): void
    {
        $reportResponse = "Ето вашата експертиза:\n\n<!-- REPORT_START -->\n<!-- REPORT_FILENAME:Ekspertiza_Test.md -->\n# ЕКСПЕРТНО ЗАКЛЮЧЕНИЕ\n\nТест\n<!-- REPORT_END -->";

        ForensicFireExpert::fake([$reportResponse]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Генерирай експертиза')
            ->call('sendMessage');

        $chatMessages = $component->get('chatMessages');
        $lastMessage = end($chatMessages);

        $this->assertStringContainsString('REPORT_START', $lastMessage['content']);
        $this->assertTrue($lastMessage['is_report']);
    }

    public function test_download_report_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index');

        $component->set('reportContent', '# Test Report');
        $component->set('reportFilename', 'test.md');

        $response = $component->call('downloadReport');
        $response->assertFileDownloaded('test.md');
    }

    public function test_download_report_pdf_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index');

        $component->set('reportContent', '# Test Report PDF');
        $component->set('reportFilename', 'test.md');

        $response = $component->call('downloadReportPdf');
        $response->assertFileDownloaded('test.pdf');
    }

    public function test_auto_title_generated_for_new_conversation(): void
    {
        ForensicFireExpert::fake([
            'Разбрах, имате пожар в къща в Славяново от небрежност.',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Имам случай на пожар в къща в село Славяново на 20.01.2026 г. от небрежност.')
            ->call('sendMessage');

        $conversationId = $component->get('conversationId');
        $this->assertNotNull($conversationId);

        $conversation = AgentConversation::find($conversationId);

        $this->assertNotNull($conversation);
    }

    public function test_report_auto_saved_to_knowledge_base(): void
    {
        Queue::fake();
        Storage::fake('local');

        $reportResponse = "<!-- REPORT_START -->\n<!-- REPORT_FILENAME:Ekspertiza_Kashta_Slavyanovo_2026-01-20_Nebrezhnost.md -->\n# ЕКСПЕРТНО ЗАКЛЮЧЕНИЕ\n\nТестово съдържание на експертиза.\n<!-- REPORT_END -->";

        ForensicFireExpert::fake([$reportResponse]);

        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Генерирай експертиза')
            ->call('sendMessage');

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'Ekspertiza_Kashta_Slavyanovo_2026-01-20_Nebrezhnost.md',
            'uploaded_by' => $user->id,
        ]);

        Queue::assertPushed(ProcessDocument::class);
    }

    public function test_duplicate_report_not_saved_twice(): void
    {
        Queue::fake();
        Storage::fake('local');

        $reportResponse = "<!-- REPORT_START -->\n<!-- REPORT_FILENAME:Ekspertiza_Duplicate.md -->\n# ЕКСПЕРТНО ЗАКЛЮЧЕНИЕ\n\nТестово съдържание.\n<!-- REPORT_END -->";

        ForensicFireExpert::fake([$reportResponse, $reportResponse]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Генерирай експертиза')
            ->call('sendMessage');

        // Send same message again to trigger duplicate
        $component->set('message', 'Генерирай експертиза')
            ->call('sendMessage');

        $this->assertDatabaseCount('documents', 1);
        Queue::assertPushed(ProcessDocument::class, 1);
    }

    public function test_conversation_status_defaults_to_pending(): void
    {
        ForensicFireExpert::fake([
            'Здравейте! Как мога да ви помогна?',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Здравейте')
            ->call('sendMessage');

        $conversationId = $component->get('conversationId');
        $conversation = AgentConversation::find($conversationId);

        $this->assertNotNull($conversation);
        $this->assertEquals(ConversationStatus::Pending, $conversation->status);
    }

    public function test_conversation_status_changes_to_completed_on_report(): void
    {
        Queue::fake();
        Storage::fake('local');

        $reportResponse = "<!-- REPORT_START -->\n<!-- REPORT_FILENAME:Ekspertiza_Test.md -->\n# ЕКСПЕРТНО ЗАКЛЮЧЕНИЕ\n\nТест\n<!-- REPORT_END -->";

        ForensicFireExpert::fake([$reportResponse]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Генерирай експертиза')
            ->call('sendMessage');

        $conversationId = $component->get('conversationId');
        $conversation = AgentConversation::find($conversationId);

        $this->assertNotNull($conversation);
        $this->assertEquals(ConversationStatus::Completed, $conversation->status);
    }

    public function test_user_message_renders_line_breaks(): void
    {
        ForensicFireExpert::fake([
            'Здравейте! Как мога да ви помогна?',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', "Първи ред\nВтори ред")
            ->call('sendMessage');

        $chatMessages = $component->get('chatMessages');
        $userMessage = $chatMessages[0];

        $this->assertEquals('user', $userMessage['role']);
        $this->assertStringContainsString("\n", $userMessage['content']);

        $component->assertSeeHtml('Първи ред<br />'."\n".'Втори ред');
    }

    public function test_send_message_recovers_gracefully_on_api_error(): void
    {
        ForensicFireExpert::fake(function (): never {
            throw new \RuntimeException('Connection refused for URI https://api.groq.com/v1/messages');
        });

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Здравейте')
            ->call('sendMessage');

        $component->assertSet('isStreaming', false);

        $chatMessages = $component->get('chatMessages');
        $lastMessage = end($chatMessages);

        $this->assertEquals('assistant', $lastMessage['role']);
        $this->assertStringContainsString(
            __('An error occurred while communicating with the AI service. Please try again.'),
            $lastMessage['content']
        );
    }

    public function test_rate_limit_falls_back_to_non_streaming_prompt(): void
    {
        $callCount = 0;

        ForensicFireExpert::fake(function () use (&$callCount): string {
            $callCount++;

            if ($callCount === 1) {
                throw RateLimitedException::forProvider('groq');
            }

            return 'Fallback response from OpenAI';
        });

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Здравейте')
            ->call('sendMessage');

        $component->assertSet('isStreaming', false);

        $chatMessages = $component->get('chatMessages');
        $lastMessage = end($chatMessages);

        $this->assertEquals('assistant', $lastMessage['role']);
        $this->assertStringContainsString('Fallback response from OpenAI', $lastMessage['content']);
    }

    public function test_empty_ai_response_shows_fallback_message(): void
    {
        ForensicFireExpert::fake(['']);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index')
            ->set('message', 'Здравейте')
            ->call('sendMessage');

        $component->assertSet('isStreaming', false);

        $chatMessages = $component->get('chatMessages');
        $lastMessage = end($chatMessages);

        $this->assertEquals('assistant', $lastMessage['role']);
        $this->assertEquals(
            'The AI service did not return a text response. Please try again.',
            $lastMessage['content']
        );
    }

    public function test_history_page_rename_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Old History Title']);

        Livewire::actingAs($user)
            ->test('pages::chat.history')
            ->call('renameConversation', $conversation->id, 'Sklad_Plovdiv_2026-02-15_KasoSaedinenie');

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Sklad_Plovdiv_2026-02-15_KasoSaedinenie',
        ]);
    }

    public function test_continuing_conversation_preserves_conversation_id(): void
    {
        ForensicFireExpert::fake([
            'Продължавам разговора за пожара.',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад']);

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id]);

        $component->assertSet('conversationId', $conversation->id);

        $component->set('message', 'Какви са причините за пожара?')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $component->assertSet('conversationId', $conversation->id);
    }

    public function test_continuing_conversation_appends_messages(): void
    {
        ForensicFireExpert::fake([
            'Причината е късо съединение в електрическата инсталация.',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад']);

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index', ['conversationId' => $conversation->id]);

        $messagesBefore = count($component->get('chatMessages'));

        $component->set('message', 'Каква е причината за пожара?')
            ->call('sendMessage');

        $messagesAfter = count($component->get('chatMessages'));

        $this->assertGreaterThan($messagesBefore, $messagesAfter);

        $chatMessages = $component->get('chatMessages');
        $lastTwo = array_slice($chatMessages, -2);

        $this->assertEquals('user', $lastTwo[0]['role']);
        $this->assertEquals('assistant', $lastTwo[1]['role']);
        $this->assertStringContainsString('късо съединение', $lastTwo[1]['content']);
    }

    public function test_conversation_summary_includes_older_user_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад']);

        // Insert 6 message pairs (12 total) — older messages that exceed the 4-message window
        for ($i = 1; $i <= 6; $i++) {
            $this->insertConversationMessage($conversation->id, $user->id, 'user', "Потребителско съобщение {$i}");
            $this->insertConversationMessage($conversation->id, $user->id, 'assistant', "Отговор на AI {$i}");
        }

        $agent = new ForensicFireExpert;
        $agent->continue($conversation->id, as: $user);

        $instructions = $agent->instructions();

        // Older user messages (outside the 4-message window) should be in the summary
        $this->assertStringContainsString('Вече събрана информация от потребителя', $instructions);
        $this->assertStringContainsString('Потребителско съобщение 1', $instructions);
        $this->assertStringContainsString('Потребителско съобщение 2', $instructions);

        // The last 4 messages (assistant 6, user 6, assistant 5, user 5) are in the window — user 5 & 6 should NOT be in summary
        $this->assertStringNotContainsString('Потребителско съобщение 6', $instructions);
    }

    public function test_conversation_summary_excludes_assistant_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад']);

        for ($i = 1; $i <= 6; $i++) {
            $this->insertConversationMessage($conversation->id, $user->id, 'user', "Потребителско съобщение {$i}");
            $this->insertConversationMessage($conversation->id, $user->id, 'assistant', "Отговор на AI {$i}");
        }

        $agent = new ForensicFireExpert;
        $agent->continue($conversation->id, as: $user);

        $instructions = $agent->instructions();

        // Assistant messages should never appear in the summary
        $this->assertStringNotContainsString('Отговор на AI 1', $instructions);
        $this->assertStringNotContainsString('Отговор на AI 2', $instructions);
    }

    public function test_conversation_summary_empty_when_few_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $conversation = AgentConversation::factory()->for($user)->create(['title' => 'Пожар в склад']);

        // Only 2 messages — within the 4-message window, so no summary needed
        $this->insertConversationMessage($conversation->id, $user->id, 'user', 'Здравейте');
        $this->insertConversationMessage($conversation->id, $user->id, 'assistant', 'Здравейте, как мога да помогна?');

        $agent = new ForensicFireExpert;
        $agent->continue($conversation->id, as: $user);

        $instructions = $agent->instructions();

        $this->assertStringNotContainsString('Вече събрана информация от потребителя', $instructions);
    }

    public function test_conversation_summary_empty_without_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $agent = new ForensicFireExpert;
        $agent->forUser($user);

        $instructions = $agent->instructions();

        $this->assertStringNotContainsString('Вече събрана информация от потребителя', $instructions);
    }

    private function insertConversationMessage(string $conversationId, int $userId, string $role, string $content): void
    {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => ForensicFireExpert::class,
            'role' => $role,
            'content' => $content,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_new_chat_creates_new_conversation(): void
    {
        ForensicFireExpert::fake([
            'Здравейте! Как мога да ви помогна?',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');

        $component = Livewire::actingAs($user)
            ->test('pages::chat.index');

        $component->set('message', 'Нов случай на пожар')
            ->call('sendMessage');

        $firstConversationId = $component->get('conversationId');
        $this->assertNotNull($firstConversationId);

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $firstConversationId,
            'user_id' => $user->id,
        ]);
    }
}
