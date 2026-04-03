<?php

namespace Tests\Unit\Services;

use App\Models\Badge;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Models\UserTopicProgress;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BadgeSeeder::class);
    }

    public function test_first_blood_awarded_on_first_pass(): void
    {
        $user = User::factory()->create();
        $topic = $this->makeTopic('core', 'cap-theorem');

        UserTopicProgress::query()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'best_score' => 85,
            'attempts_count' => 1,
            'passed' => true,
            'passed_at' => now(),
        ]);

        $attempt = $this->makeAttempt($user, $topic, 85, true, now()->subMinutes(5), now());

        $awarded = app(BadgeService::class)->checkAndAward($user, $attempt);

        $this->assertContains('first-blood', array_column($awarded, 'slug'));
        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('slug', 'first-blood')->value('id'),
        ]);
    }

    public function test_perfect_100_awarded_on_perfect_attempt(): void
    {
        $user = User::factory()->create();
        $topic = $this->makeTopic('core', 'replication');

        UserTopicProgress::query()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'best_score' => 100,
            'attempts_count' => 1,
            'passed' => true,
            'passed_at' => now(),
        ]);

        $attempt = $this->makeAttempt($user, $topic, 100, true, now()->subMinutes(4), now());

        $awarded = app(BadgeService::class)->checkAndAward($user, $attempt);

        $this->assertContains('perfect-100', array_column($awarded, 'slug'));
    }

    public function test_streak_seven_awarded_when_current_streak_is_seven(): void
    {
        $user = User::factory()->create(['current_streak' => 7]);
        $topic = $this->makeTopic('core', 'sharding');
        $attempt = $this->makeAttempt($user, $topic, 79, false, now()->subMinutes(8), now());

        $awarded = app(BadgeService::class)->checkAndAward($user, $attempt);

        $this->assertContains('streak-7', array_column($awarded, 'slug'));
    }

    public function test_comeback_kid_requires_prior_failure_on_same_topic(): void
    {
        $user = User::factory()->create();
        $topic = $this->makeTopic('core', 'consistent-hashing');

        $this->makeAttempt($user, $topic, 70, false, now()->subDays(1)->subMinutes(3), now()->subDays(1));

        UserTopicProgress::query()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'best_score' => 82,
            'attempts_count' => 2,
            'passed' => true,
            'passed_at' => now(),
        ]);

        $passingAttempt = $this->makeAttempt($user, $topic, 82, true, now()->subMinutes(5), now());

        $awarded = app(BadgeService::class)->checkAndAward($user, $passingAttempt);

        $this->assertContains('comeback-kid', array_column($awarded, 'slug'));
    }

    public function test_no_duplicate_badge_awards(): void
    {
        $user = User::factory()->create();
        $topic = $this->makeTopic('core', 'circuit-breaker');

        UserTopicProgress::query()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'best_score' => 90,
            'attempts_count' => 1,
            'passed' => true,
            'passed_at' => now(),
        ]);

        $attempt = $this->makeAttempt($user, $topic, 90, true, now()->subMinutes(5), now());

        app(BadgeService::class)->checkAndAward($user, $attempt);
        $awardedSecondCall = app(BadgeService::class)->checkAndAward($user, $attempt);

        $this->assertSame([], $awardedSecondCall);
        $this->assertSame(
            1,
            (int) \App\Models\UserBadge::query()
                ->where('user_id', $user->id)
                ->where('badge_id', Badge::query()->where('slug', 'first-blood')->value('id'))
                ->count()
        );
    }

    public function test_speed_runner_requires_under_three_minutes(): void
    {
        $fastUser = User::factory()->create();
        $slowUser = User::factory()->create();

        $topicA = $this->makeTopic('core', 'rest');
        $topicB = $this->makeTopic('advanced', 'api-gateway');

        $fastStartedAt = Carbon::create(2026, 4, 3, 10, 0, 0, 'UTC');
        $fastCompletedAt = Carbon::create(2026, 4, 3, 10, 2, 59, 'UTC');
        $slowStartedAt = Carbon::create(2026, 4, 3, 11, 0, 0, 'UTC');
        $slowCompletedAt = Carbon::create(2026, 4, 3, 11, 3, 1, 'UTC');

        $fastAttempt = $this->makeAttempt(
            $fastUser,
            $topicA,
            85,
            true,
            $fastStartedAt,
            $fastCompletedAt
        );

        $slowAttempt = $this->makeAttempt(
            $slowUser,
            $topicB,
            85,
            true,
            $slowStartedAt,
            $slowCompletedAt
        );

        $this->assertSame(179, (int) abs($fastAttempt->completed_at->diffInSeconds($fastAttempt->started_at)));
        $this->assertSame(181, (int) abs($slowAttempt->completed_at->diffInSeconds($slowAttempt->started_at)));

        $fastAwarded = app(BadgeService::class)->checkAndAward($fastUser, $fastAttempt);
        $slowAwarded = app(BadgeService::class)->checkAndAward($slowUser, $slowAttempt);

        $this->assertContains('speed-runner', array_column($fastAwarded, 'slug'));
        $this->assertNotContains('speed-runner', array_column($slowAwarded, 'slug'));
    }

    private function makeTopic(string $section, string $slug): Topic
    {
        return Topic::query()->create([
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'category' => 'Test Category',
            'section' => $section,
            'level' => 'Intermediate',
            'hook_question' => 'How would you design this?',
            'description' => 'Test topic description',
            'key_points' => ['a', 'b'],
            'sort_order' => 1,
        ]);
    }

    private function makeAttempt(
        User $user,
        Topic $topic,
        int $score,
        bool $passed,
        \DateTimeInterface $startedAt,
        \DateTimeInterface $completedAt,
    ): TopicAttempt {
        return TopicAttempt::query()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => 'Test answer',
            'score' => $score,
            'passed' => $passed,
            'evaluation' => ['notes' => 'Notes'],
            'status' => 'complete',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
    }
}
