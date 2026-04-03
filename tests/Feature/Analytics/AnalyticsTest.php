<?php

namespace Tests\Feature\Analytics;

use App\DTO\EvaluationResult;
use App\Jobs\EvaluateAttemptJob;
use App\Models\StreakLog;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Models\UserTopicProgress;
use App\Services\Evaluation\AttemptEvaluationService;
use App\Services\Progress\UserProgressService;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\TopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TopicSeeder::class);
        $this->seed(BadgeSeeder::class);
    }

    public function test_returns_correct_counts_after_seeding_attempts(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $coreTopic = Topic::query()->where('section', 'core')->firstOrFail();
        $advancedTopic = Topic::query()->where('section', 'advanced')->firstOrFail();

        UserTopicProgress::query()->create([
            'user_id' => $user->id,
            'topic_id' => $coreTopic->id,
            'best_score' => 90,
            'attempts_count' => 2,
            'passed' => true,
            'passed_at' => now(),
        ]);

        UserTopicProgress::query()->create([
            'user_id' => $user->id,
            'topic_id' => $advancedTopic->id,
            'best_score' => 85,
            'attempts_count' => 1,
            'passed' => true,
            'passed_at' => now(),
        ]);

        TopicAttempt::query()->create([
            'user_id' => $user->id,
            'topic_id' => $coreTopic->id,
            'answer_text' => 'Attempt 1',
            'score' => 70,
            'passed' => false,
            'status' => 'complete',
            'started_at' => now()->subMinutes(9),
            'completed_at' => now()->subMinutes(6),
        ]);

        TopicAttempt::query()->create([
            'user_id' => $user->id,
            'topic_id' => $coreTopic->id,
            'answer_text' => 'Attempt 2',
            'score' => 90,
            'passed' => true,
            'status' => 'complete',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(2),
        ]);

        TopicAttempt::query()->create([
            'user_id' => $user->id,
            'topic_id' => $advancedTopic->id,
            'answer_text' => 'Attempt 3',
            'score' => 85,
            'passed' => true,
            'status' => 'complete',
            'started_at' => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/analytics');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'progress' => ['total', 'passed', 'core_passed', 'advanced_passed', 'completion_pct'],
                    'streak' => ['current', 'longest', 'last_active'],
                    'category_breakdown',
                    'score_timeline',
                    'weak_areas',
                    'time_spent',
                    'activity_calendar',
                ],
            ])
            ->assertJsonPath('data.progress.total', 53)
            ->assertJsonPath('data.progress.passed', 2)
            ->assertJsonPath('data.progress.core_passed', 1)
            ->assertJsonPath('data.progress.advanced_passed', 1);
    }

    public function test_activity_calendar_has_correct_date_entries(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        StreakLog::query()->create([
            'user_id' => $user->id,
            'activity_date' => now()->subDays(2)->toDateString(),
        ]);

        StreakLog::query()->create([
            'user_id' => $user->id,
            'activity_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/analytics');

        $response->assertOk();

        $calendar = $response->json('data.activity_calendar');

        $this->assertContains(now()->subDays(2)->toDateString(), array_column($calendar, 'date'));
        $this->assertContains(now()->subDay()->toDateString(), array_column($calendar, 'date'));
    }

    public function test_cache_is_set_after_first_call(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->getJson('/api/v1/analytics')->assertOk();

        $this->assertTrue(Cache::has("analytics:user:{$user->id}"));
    }

    public function test_cache_is_cleared_after_new_attempt_processed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::query()->firstOrFail();

        $attempt = TopicAttempt::query()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => 'Pending attempt',
            'status' => 'pending',
            'started_at' => now()->subMinutes(4),
        ]);

        Cache::put("analytics:user:{$user->id}", ['cached' => true], 300);
        Cache::put("progress:user:{$user->id}", ['cached' => true], 300);

        $evaluationService = Mockery::mock(AttemptEvaluationService::class);
        $evaluationService->shouldReceive('evaluate')->once()->andReturn(
            new EvaluationResult(
                score: 88,
                passed: true,
                keyStrengths: ['Strong reasoning'],
                keyWeaknesses: ['Needs examples'],
                conceptsToStudy: ['Consistency'],
                briefAssessment: 'Solid answer',
                promptToExplain: 'Explain consistency models',
                promptToNext: 'Move to quorums',
                modelAnswer: 'Model answer',
                ragSources: [],
                rawEvaluation: [],
            )
        );

        $progressService = Mockery::mock(UserProgressService::class);
        $progressService->shouldReceive('updateAfterAttempt')->once()->with($user->id, $topic->id, 88);

        $job = new EvaluateAttemptJob($attempt->id);
        $job->handle($evaluationService, $progressService);

        $this->assertFalse(Cache::has("analytics:user:{$user->id}"));
        $this->assertFalse(Cache::has("progress:user:{$user->id}"));
    }
}
