<?php

namespace Tests\Feature\Topics;

use App\Models\Topic;
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

    public function test_topic_seeder_clears_cache(): void
    {
        Cache::put('topics:all', 'stale');

        $this->seed(\Database\Seeders\TopicSeeder::class);

        $this->assertFalse(Cache::has('topics:all'));
    }
}
