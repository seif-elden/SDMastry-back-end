<?php

namespace Tests\Feature\Attempts;

use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TopicSeeder::class);
    }

    public function test_owner_can_poll_attempt_status(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer content here. ', 5),
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/attempts/{$attempt->id}/status");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'attempt_id' => $attempt->id,
                    'status' => 'pending',
                ],
            ]);
    }

    public function test_non_owner_gets_404(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        $attempt = TopicAttempt::create([
            'user_id' => $owner->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer content here. ', 5),
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($other)
            ->getJson("/api/v1/attempts/{$attempt->id}/status");

        $response->assertStatus(404);
    }

    public function test_full_attempt_detail_returns_evaluation(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer content here. ', 5),
            'status' => 'completed',
            'score' => 80,
            'passed' => true,
            'evaluation' => ['feedback' => 'Good job'],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/attempts/{$attempt->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $attempt->id,
                    'score' => 80,
                    'passed' => true,
                    'status' => 'completed',
                    'evaluation' => ['feedback' => 'Good job'],
                ],
            ]);
    }

    public function test_non_owner_cannot_view_attempt_detail(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::first();

        $attempt = TopicAttempt::create([
            'user_id' => $owner->id,
            'topic_id' => $topic->id,
            'answer_text' => str_repeat('Answer content here. ', 5),
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($other)
            ->getJson("/api/v1/attempts/{$attempt->id}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_cannot_view_status(): void
    {
        $response = $this->getJson('/api/v1/attempts/1/status');
        $response->assertStatus(401);
    }
}
