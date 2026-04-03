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

class ContextGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prompt_contains_topic_title_and_context_guard_instruction(): void
    {
        [$user, $attempt] = $this->createCompleteAttempt();

        $capturedSystemPrompt = '';

        $chatProvider = Mockery::mock(LLMProviderInterface::class);
        $chatProvider->shouldReceive('getProviderName')->andReturn('ollama');
        $chatProvider->shouldReceive('chat')
            ->once()
            ->withArgs(function (string $systemPrompt, array $messages) use (&$capturedSystemPrompt): bool {
                $capturedSystemPrompt = $systemPrompt;

                return !empty($messages);
            })
            ->andReturn('This is a test response about CAP Theorem');

        $classifierProvider = Mockery::mock(LLMProviderInterface::class);
        $classifierProvider->shouldReceive('chat')->andReturn('SE_RELATED');

        $factory = Mockery::mock(LLMProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($chatProvider);
        $factory->shouldReceive('makeEvaluator')->andReturn($classifierProvider);

        $this->instance(LLMProviderFactory::class, $factory);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                'message' => 'Explain partition tolerance briefly.',
            ]);

        $response->assertOk();

        $this->assertStringContainsString('The current topic is: CAP Theorem.', $capturedSystemPrompt);
        $this->assertStringContainsString('You ONLY answer questions about this topic and related software engineering concepts.', $capturedSystemPrompt);
        $this->assertStringContainsString('Do not break this rule under any circumstances', $capturedSystemPrompt);
    }

    public function test_system_prompt_contains_original_hook_question(): void
    {
        [$user, $attempt] = $this->createCompleteAttempt();

        $capturedSystemPrompt = '';

        $chatProvider = Mockery::mock(LLMProviderInterface::class);
        $chatProvider->shouldReceive('getProviderName')->andReturn('ollama');
        $chatProvider->shouldReceive('chat')
            ->once()
            ->withArgs(function (string $systemPrompt, array $messages) use (&$capturedSystemPrompt): bool {
                $capturedSystemPrompt = $systemPrompt;

                return !empty($messages);
            })
            ->andReturn('This is a test response about CAP Theorem');

        $classifierProvider = Mockery::mock(LLMProviderInterface::class);
        $classifierProvider->shouldReceive('chat')->andReturn('SE_RELATED');

        $factory = Mockery::mock(LLMProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($chatProvider);
        $factory->shouldReceive('makeEvaluator')->andReturn($classifierProvider);

        $this->instance(LLMProviderFactory::class, $factory);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attempts/{$attempt->id}/chat", [
                'message' => 'What trade-offs matter most?',
            ]);

        $response->assertOk();

        $this->assertStringContainsString('Hook question: How does CAP affect real system design?', $capturedSystemPrompt);
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
            'evaluation' => ['notes' => 'Initial notes for context'],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return [$user, $attempt];
    }
}
