<?php

namespace Tests\Feature\Topics;

use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Models\UserTopicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TopicsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TopicSeeder::class);
    }

    public function test_unauthenticated_user_gets_topics_with_locked_true(): void
    {
        $response = $this->getJson('/api/v1/topics');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'slug', 'title', 'category', 'section', 'level', 'hook_question', 'sort_order', 'locked'],
                ],
            ]);

        $first = $response->json('data.0');
        $this->assertTrue($first['locked']);
        $this->assertArrayNotHasKey('progress', $first);
    }

    public function test_unverified_user_gets_topics_with_locked_true(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user)->getJson('/api/v1/topics');

        $response->assertOk();
        $first = $response->json('data.0');
        $this->assertTrue($first['locked']);
    }

    public function test_unverified_user_still_gets_progress_overlay_when_attempt_history_exists(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $topic = Topic::first();

        TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => 'Progress for unverified user.',
            'score' => 90,
            'passed' => true,
            'status' => 'complete',
            'started_at' => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(3),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/topics');

        $response->assertOk();

        $topicData = collect($response->json('data'))->firstWhere('id', $topic->id);
        $this->assertTrue($topicData['locked']);
        $this->assertNotNull($topicData['progress']);
        $this->assertEquals(90, $topicData['progress']['best_score']);
        $this->assertEquals(1, $topicData['progress']['attempts_count']);
        $this->assertTrue($topicData['progress']['passed']);
    }

    public function test_verified_user_gets_topics_with_progress_overlay(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        UserTopicProgress::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'best_score' => 85,
            'attempts_count' => 3,
            'passed' => true,
            'passed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/topics');

        $response->assertOk();

        $topicData = collect($response->json('data'))->firstWhere('id', $topic->id);
        $this->assertNotNull($topicData['progress']);
        $this->assertEquals(85, $topicData['progress']['best_score']);
        $this->assertEquals(3, $topicData['progress']['attempts_count']);
        $this->assertTrue($topicData['progress']['passed']);
        $this->assertArrayNotHasKey('locked', $topicData);
    }

    public function test_topics_are_cached(): void
    {
        Cache::forget('topics:all');

        $this->getJson('/api/v1/topics')->assertOk();

        $this->assertTrue(Cache::has('topics:all'));
    }

    public function test_verified_user_gets_derived_progress_from_attempts_when_progress_row_missing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => 'Derived progress answer',
            'score' => 90,
            'passed' => true,
            'status' => 'complete',
            'started_at' => now()->subMinutes(3),
            'completed_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/topics');

        $response->assertOk();

        $topicData = collect($response->json('data'))->firstWhere('id', $topic->id);
        $this->assertNotNull($topicData['progress']);
        $this->assertEquals(90, $topicData['progress']['best_score']);
        $this->assertEquals(1, $topicData['progress']['attempts_count']);
        $this->assertTrue($topicData['progress']['passed']);
    }

    public function test_topic_seeder_clears_cache(): void
    {
        Cache::put('topics:all', 'stale');

        $this->seed(\Database\Seeders\TopicSeeder::class);

        $this->assertFalse(Cache::has('topics:all'));
    }

    public function test_bearer_token_user_gets_unlocked_topics_and_progress(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => 'Bearer token progress answer',
            'score' => 94,
            'passed' => true,
            'status' => 'complete',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ]);

        $token = $user->createToken('topics-index-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/topics');

        $response->assertOk();

        $topicData = collect($response->json('data'))->firstWhere('id', $topic->id);
        $this->assertArrayNotHasKey('locked', $topicData);
        $this->assertNotNull($topicData['progress']);
        $this->assertEquals(94, $topicData['progress']['best_score']);
        $this->assertEquals(1, $topicData['progress']['attempts_count']);
        $this->assertTrue($topicData['progress']['passed']);
    }
}
