<?php

namespace Tests\Feature\Topics;

use App\Models\Topic;
use App\Models\User;
use App\Models\UserTopicProgress;
use App\Services\Progress\UserProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TopicSeeder::class);
    }

    public function test_returns_correct_progress_counts(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $coreTopic = Topic::where('section', 'core')->first();
        $advancedTopic = Topic::where('section', 'advanced')->first();

        if ($coreTopic) {
            UserTopicProgress::create([
                'user_id' => $user->id,
                'topic_id' => $coreTopic->id,
                'best_score' => 90,
                'attempts_count' => 2,
                'passed' => true,
                'passed_at' => now(),
            ]);
        }

        if ($advancedTopic) {
            UserTopicProgress::create([
                'user_id' => $user->id,
                'topic_id' => $advancedTopic->id,
                'best_score' => 70,
                'attempts_count' => 1,
                'passed' => true,
                'passed_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/progress');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => ['total_topics', 'passed', 'core_passed', 'advanced_passed', 'best_scores'],
            ]);

        $data = $response->json('data');
        $expectedPassed = ($coreTopic ? 1 : 0) + ($advancedTopic ? 1 : 0);
        $this->assertEquals($expectedPassed, $data['passed']);

        if ($coreTopic) {
            $this->assertEquals(1, $data['core_passed']);
        }
        if ($advancedTopic) {
            $this->assertEquals(1, $data['advanced_passed']);
        }
    }

    public function test_progress_is_cached(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->getJson('/api/v1/progress')->assertOk();

        $this->assertTrue(Cache::has("progress:user:{$user->id}"));
    }

    public function test_cache_invalidated_after_attempt_update(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        // Prime the cache
        $this->actingAs($user)->getJson('/api/v1/progress')->assertOk();
        $this->assertTrue(Cache::has("progress:user:{$user->id}"));

        // Simulate what the job does
        $service = app(UserProgressService::class);
        $service->updateAfterAttempt($user->id, $topic->id, 80);

        $this->assertFalse(Cache::has("progress:user:{$user->id}"));
    }

    public function test_unauthenticated_cannot_view_progress(): void
    {
        $response = $this->getJson('/api/v1/progress');
        $response->assertStatus(401);
    }

    public function test_empty_progress_returns_zeros(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/progress');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['passed']);
        $this->assertEquals(0, $data['core_passed']);
        $this->assertEquals(0, $data['advanced_passed']);
        $this->assertEmpty($data['best_scores']);
        $this->assertGreaterThan(0, $data['total_topics']);
    }
}
