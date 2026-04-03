<?php

namespace Tests\Feature\Chat;

use App\DTO\EvaluationResult;
use App\Jobs\EvaluateAttemptJob;
use App\Jobs\StoreModelAnswerInRagJob;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Services\Evaluation\AttemptEvaluationService;
use App\Services\Progress\UserProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_created_when_attempt_completes_with_notes_message(): void
    {
        Bus::fake([StoreModelAnswerInRagJob::class]);

        [$attempt, $evaluationService, $progressService] = $this->arrangeDependencies();

        $progressService->shouldReceive('updateAfterAttempt')->once();
        $evaluationService->shouldReceive('evaluate')->once()->andReturn($this->fakeResult());

        $job = new EvaluateAttemptJob($attempt->id);
        $job->handle($evaluationService, $progressService);

        $attempt->refresh();
        $attempt->load('chatSession.messages');

        $this->assertNotNull($attempt->chatSession);
        $this->assertCount(1, $attempt->chatSession->messages);
        $this->assertSame('assistant', $attempt->chatSession->messages->first()->role);
        $this->assertSame('Notes seed for chat.', $attempt->chatSession->messages->first()->content);
    }

    public function test_owner_can_get_messages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = $this->createTopic();

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer about CAP theorem. ', 5),
            'status' => 'complete',
            'evaluation' => ['notes' => 'Notes from evaluation'],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $session = $attempt->chatSession()->create(['created_at' => now()]);
        $session->messages()->create([
            'role' => 'assistant',
            'content' => 'Notes from evaluation',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/attempts/{$attempt->id}/chat");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                ],
            ])
            ->assertJsonPath('data.messages.0.role', 'assistant');
    }

    public function test_non_owner_gets_403(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $topic = $this->createTopic();

        $attempt = TopicAttempt::create([
            'user_id' => $owner->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer about CAP theorem. ', 5),
            'status' => 'complete',
            'evaluation' => ['notes' => 'Notes from evaluation'],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($other)
            ->getJson("/api/v1/attempts/{$attempt->id}/chat");

        $response->assertStatus(403);
    }

    public function test_pending_attempt_returns_409(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = $this->createTopic();

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer about CAP theorem. ', 5),
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/attempts/{$attempt->id}/chat");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Evaluation still in progress',
            ]);
    }

    /**
     * @return array{TopicAttempt, AttemptEvaluationService&MockInterface, UserProgressService&MockInterface}
     */
    private function arrangeDependencies(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = $this->createTopic();

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Attempt answer text. ', 5),
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $evaluationService = Mockery::mock(AttemptEvaluationService::class);
        $progressService = Mockery::mock(UserProgressService::class);

        return [$attempt, $evaluationService, $progressService];
    }

    private function createTopic(): Topic
    {
        return Topic::create([
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
    }

    private function fakeResult(): EvaluationResult
    {
        return new EvaluationResult(
            score: 88,
            passed: true,
            keyStrengths: ['Strength'],
            keyWeaknesses: ['Weakness'],
            conceptsToStudy: ['Concept'],
            briefAssessment: 'Assessment',
            promptToExplain: 'Explain',
            promptToNext: 'Next',
            notes: 'Notes seed for chat.',
            ragSources: [['book' => 'DDIA', 'relevance' => '90%']],
            rawEvaluation: [],
        );
    }
}
