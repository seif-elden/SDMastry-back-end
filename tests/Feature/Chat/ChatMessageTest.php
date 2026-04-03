<?php

namespace Tests\Feature\Chat;

use App\Contracts\LLMProviderInterface;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Services\LLM\LLMProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_message_stores_user_message_calls_llm_and_stores_reply(): void
    {
        [$user, $attempt] = $this->createCompleteAttempt();
        $this->mockChatProviders('This is a test response about CAP Theorem', 'SE_RELATED');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                'message' => 'Can you explain consistency again?',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message.role', 'assistant')
            ->assertJsonPath('data.message.content', 'This is a test response about CAP Theorem');

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $attempt->chatSession->id,
            'role' => 'user',
            'content' => 'Can you explain consistency again?',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $attempt->chatSession->id,
            'role' => 'assistant',
            'content' => 'This is a test response about CAP Theorem',
        ]);
    }

    public function test_rate_limit_rejects_21st_message_in_minute(): void
    {
        [$user, $attempt] = $this->createCompleteAttempt();
        $this->mockChatProviders('This is a test response about CAP Theorem', 'SE_RELATED');

        for ($i = 0; $i < 20; $i++) {
            $response = $this->actingAs($user)
                ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                    'message' => 'Message ' . $i,
                ]);

            $response->assertOk();
        }

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                'message' => 'Message 21',
            ]);

        $response->assertStatus(429);
    }

    public function test_message_over_2000_chars_rejected(): void
    {
        [$user, $attempt] = $this->createCompleteAttempt();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                'message' => str_repeat('a', 2001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_non_owner_attempt_returns_403(): void
    {
        [$owner, $attempt] = $this->createCompleteAttempt();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->mockChatProviders('This is a test response about CAP Theorem', 'SE_RELATED');

        $response = $this->actingAs($other)
            ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                'message' => 'Can you explain this?',
            ]);

        $response->assertStatus(403);
    }

    private function createCompleteAttempt(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $topic = Topic::create([
            'slug' => 'cap-theorem',
            'title' => 'CAP Theorem',
            'category' => 'distributed-systems',
            'section' => 'core',
            'level' => 'intermediate',
            'hook_question' => 'How does CAP affect real system design?',
            'description' => 'Trade-offs between consistency, availability, and partition tolerance.',
            'key_points' => ['consistency', 'availability', 'partition tolerance'],
            'sort_order' => 1,
        ]);

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Attempt answer text. ', 5),
            'status' => 'complete',
            'evaluation' => ['model_answer' => 'Initial model answer for context'],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $attempt->chatSession()->create(['created_at' => now()]);
        $attempt->refresh();

        return [$user, $attempt];
    }

    private function mockChatProviders(string $reply, string $classification): void
    {
        $chatProvider = Mockery::mock(LLMProviderInterface::class);
        $chatProvider->shouldReceive('chat')->andReturn($reply);
        $chatProvider->shouldReceive('getProviderName')->andReturn('ollama');

        $classifierProvider = Mockery::mock(LLMProviderInterface::class);
        $classifierProvider->shouldReceive('chat')->andReturn($classification);

        $factory = Mockery::mock(LLMProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($chatProvider);
        $factory->shouldReceive('makeEvaluator')->andReturn($classifierProvider);

        $this->instance(LLMProviderFactory::class, $factory);
    }
}
