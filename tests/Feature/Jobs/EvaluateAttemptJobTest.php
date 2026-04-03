<?php

namespace Tests\Feature\Jobs;

use App\DTO\EvaluationResult;
use App\Jobs\EvaluateAttemptJob;
use App\Jobs\StoreModelAnswerInRagJob;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Services\Evaluation\AttemptEvaluationService;
use App\Services\Progress\UserProgressService;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EvaluateAttemptJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_transitions_pending_to_evaluating_to_complete(): void
    {
        Bus::fake([StoreModelAnswerInRagJob::class]);

        [$attempt, $evaluationService, $progressService] = $this->arrangeSuccessfulDependencies();

        $progressService->shouldReceive('updateAfterAttempt')->once()->with($attempt->user_id, $attempt->topic_id, Mockery::type('int'));

        $evaluationService->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::on(fn ($passedAttempt) => $passedAttempt->status === 'evaluating'))
            ->andReturn($this->fakeResult(88, true));

        $job = new EvaluateAttemptJob($attempt->id);
        $job->handle($evaluationService, $progressService);

        $attempt->refresh();

        $this->assertSame('complete', $attempt->status);
        $this->assertSame(88, $attempt->score);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->completed_at);
    }

    public function test_attempt_transitions_to_failed_on_final_exception(): void
    {
        [$attempt, $evaluationService, $progressService] = $this->arrangeSuccessfulDependencies();

        $evaluationService->shouldReceive('evaluate')->once()->andThrow(new \RuntimeException('Evaluator timeout'));
        $progressService->shouldReceive('updateAfterAttempt')->never();

        $job = new EvaluateAttemptJob($attempt->id);
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $job->setJob($queueJob);

        try {
            $job->handle($evaluationService, $progressService);
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Evaluator timeout', $e->getMessage());
        }

        $attempt->refresh();

        $this->assertSame('failed', $attempt->status);
        $this->assertSame('Evaluator timeout', $attempt->evaluation['error']);
    }

    public function test_attempt_remains_evaluating_on_non_final_exception(): void
    {
        [$attempt, $evaluationService, $progressService] = $this->arrangeSuccessfulDependencies();

        $evaluationService->shouldReceive('evaluate')->once()->andThrow(new \RuntimeException('Temporary timeout'));
        $progressService->shouldReceive('updateAfterAttempt')->never();

        $job = new EvaluateAttemptJob($attempt->id);
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $job->setJob($queueJob);

        try {
            $job->handle($evaluationService, $progressService);
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Temporary timeout', $e->getMessage());
        }

        $attempt->refresh();

        $this->assertSame('evaluating', $attempt->status);
        $this->assertNull($attempt->evaluation);
    }

    public function test_cache_is_cleared_after_completion(): void
    {
        Bus::fake([StoreModelAnswerInRagJob::class]);

        [$attempt, $evaluationService, $progressService] = $this->arrangeSuccessfulDependencies();

        $progressService->shouldReceive('updateAfterAttempt')->once()->with($attempt->user_id, $attempt->topic_id, Mockery::type('int'));

        Cache::put("progress:user:{$attempt->user_id}", ['x' => 1], 300);
        Cache::put("attempt:status:{$attempt->id}", ['status' => 'pending'], 300);

        $evaluationService->shouldReceive('evaluate')->once()->andReturn($this->fakeResult(82, true));

        $job = new EvaluateAttemptJob($attempt->id);
        $job->handle($evaluationService, $progressService);

        $this->assertNull(Cache::get("progress:user:{$attempt->user_id}"));
        $this->assertNull(Cache::get("attempt:status:{$attempt->id}"));
    }

    public function test_dispatches_store_model_answer_job(): void
    {
        Bus::fake([StoreModelAnswerInRagJob::class]);

        [$attempt, $evaluationService, $progressService] = $this->arrangeSuccessfulDependencies();

        $progressService->shouldReceive('updateAfterAttempt')->once()->with($attempt->user_id, $attempt->topic_id, Mockery::type('int'));

        $evaluationService->shouldReceive('evaluate')->once()->andReturn($this->fakeResult(91, true));

        $job = new EvaluateAttemptJob($attempt->id);
        $job->handle($evaluationService, $progressService);

        Bus::assertDispatched(StoreModelAnswerInRagJob::class, function (StoreModelAnswerInRagJob $job) use ($attempt) {
            return $job->attemptId === $attempt->id
                && $job->topicId === $attempt->topic_id
                && $job->modelAnswer !== '';
        });
    }

    /**
    * @return array{TopicAttempt, AttemptEvaluationService&MockInterface, UserProgressService&MockInterface}
     */
    private function arrangeSuccessfulDependencies(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $topic = Topic::create([
            'slug' => 'cap-theorem',
            'title' => 'CAP Theorem',
            'category' => 'distributed-systems',
            'section' => 'core',
            'level' => 'intermediate',
            'hook_question' => 'Explain CAP trade-offs.',
            'description' => 'Trade-offs in distributed systems',
            'key_points' => ['consistency', 'availability', 'partition tolerance'],
            'sort_order' => 1,
        ]);

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('My answer. ', 6),
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $evaluationService = Mockery::mock(AttemptEvaluationService::class);
        $progressService = Mockery::mock(UserProgressService::class);

        return [$attempt, $evaluationService, $progressService];
    }

    private function fakeResult(int $score, bool $passed): EvaluationResult
    {
        return new EvaluationResult(
            score: $score,
            passed: $passed,
            keyStrengths: ['Good system design reasoning'],
            keyWeaknesses: ['Could detail partition scenario'],
            conceptsToStudy: ['Consistency levels'],
            briefAssessment: 'Strong but could be deeper.',
            promptToExplain: 'Ask me to explain consistency levels in detail',
            promptToNext: "Ready for quorum systems? Let's try it.",
            modelAnswer: 'Model answer text',
            ragSources: [['book' => 'DDIA', 'relevance' => '90%']],
            rawEvaluation: [],
        );
    }
}
