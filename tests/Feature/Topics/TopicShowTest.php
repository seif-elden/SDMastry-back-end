<?php

namespace Tests\Feature\Topics;

use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TopicSeeder::class);
    }

    public function test_can_get_topic_by_slug(): void
    {
        $topic = Topic::first();

        $response = $this->getJson("/api/v1/topics/{$topic->slug}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => $topic->slug,
                    'title' => $topic->title,
                    'description' => $topic->description,
                ],
            ])
            ->assertJsonStructure([
                'data' => ['id', 'slug', 'title', 'category', 'section', 'level', 'hook_question', 'description', 'key_points', 'sort_order'],
            ]);
    }

    public function test_invalid_slug_returns_404(): void
    {
        $response = $this->getJson('/api/v1/topics/non-existent-topic-slug');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Topic not found.',
            ]);
    }

    public function test_verified_user_sees_attempt_history(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Test answer content. ', 10),
            'status' => 'completed',
            'score' => 80,
            'passed' => true,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/topics/{$topic->slug}");

        $response->assertOk();
        $attempts = $response->json('data.attempts');
        $this->assertCount(1, $attempts);
        $this->assertEquals(80, $attempts[0]['score']);
    }

    public function test_unauthenticated_user_does_not_see_attempts(): void
    {
        $topic = Topic::first();

        $response = $this->getJson("/api/v1/topics/{$topic->slug}");

        $response->assertOk();
        $this->assertArrayNotHasKey('attempts', $response->json('data'));
    }
}
