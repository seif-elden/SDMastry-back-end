<?php

namespace Tests\Feature\Attempts;

use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TopicSeeder::class);
    }

    public function test_returns_only_own_attempts(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('My answer. ', 10),
            'status' => 'completed',
            'started_at' => now(),
        ]);

        TopicAttempt::create([
            'user_id' => $other->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Other answer. ', 10),
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/topics/{$topic->slug}/attempts");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $attempts = $response->json('data.attempts');
        $this->assertCount(1, $attempts);
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        for ($i = 0; $i < 15; $i++) {
            TopicAttempt::create([
                'user_id' => $user->id,
                'topic_id' => $topic->id,
                'answer_text' => str_repeat('Answer number ' . $i . '. ', 10),
                'status' => 'completed',
                'started_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson("/api/v1/topics/{$topic->slug}/attempts");

        $response->assertOk();
        $this->assertCount(10, $response->json('data.attempts'));
        $this->assertEquals(15, $response->json('data.pagination.total'));
        $this->assertEquals(2, $response->json('data.pagination.last_page'));

        // Page 2
        $response2 = $this->actingAs($user)
            ->getJson("/api/v1/topics/{$topic->slug}/attempts?page=2");

        $response2->assertOk();
        $this->assertCount(5, $response2->json('data.attempts'));
    }

    public function test_invalid_topic_returns_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/topics/nonexistent-slug/attempts');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_cannot_list_attempts(): void
    {
        $topic = Topic::first();

        $response = $this->getJson("/api/v1/topics/{$topic->slug}/attempts");

        $response->assertStatus(401);
    }
}
