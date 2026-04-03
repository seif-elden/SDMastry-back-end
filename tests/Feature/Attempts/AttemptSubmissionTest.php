<?php

namespace Tests\Feature\Attempts;

use App\Jobs\EvaluateAttemptJob;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AttemptSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $verifiedUser;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TopicSeeder::class);
        $this->verifiedUser = User::factory()->create(['email_verified_at' => now()]);
        $this->topic = Topic::first();
    }

    public function test_verified_user_can_submit_attempt(): void
    {
        Bus::fake([EvaluateAttemptJob::class]);

        $response = $this->actingAs($this->verifiedUser)
            ->postJson("/api/v1/topics/{$this->topic->slug}/attempts", [
                'answer' => str_repeat('This is a detailed answer about the topic. ', 5),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['attempt_id', 'status'],
            ]);

        $this->assertDatabaseHas('topic_attempts', [
            'user_id' => $this->verifiedUser->id,
            'topic_id' => $this->topic->id,
            'status' => 'pending',
        ]);

        Bus::assertDispatched(EvaluateAttemptJob::class);
    }

    public function test_unverified_user_gets_403(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($unverified)
            ->postJson("/api/v1/topics/{$this->topic->slug}/attempts", [
                'answer' => str_repeat('This is a detailed answer about the topic. ', 5),
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Please verify your email to attempt topics.',
            ]);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->postJson("/api/v1/topics/{$this->topic->slug}/attempts", [
            'answer' => str_repeat('This is a detailed answer about the topic. ', 5),
        ]);

        $response->assertStatus(401);
    }

    public function test_too_short_answer_rejected(): void
    {
        $response = $this->actingAs($this->verifiedUser)
            ->postJson("/api/v1/topics/{$this->topic->slug}/attempts", [
                'answer' => 'Too short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answer']);
    }

    public function test_missing_answer_rejected(): void
    {
        $response = $this->actingAs($this->verifiedUser)
            ->postJson("/api/v1/topics/{$this->topic->slug}/attempts", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answer']);
    }

    public function test_invalid_topic_slug_returns_404(): void
    {
        $response = $this->actingAs($this->verifiedUser)
            ->postJson('/api/v1/topics/nonexistent-slug/attempts', [
                'answer' => str_repeat('This is a detailed answer about the topic. ', 5),
            ]);

        $response->assertStatus(404);
    }

    public function test_rate_limiting_after_max_attempts(): void
    {
        $limit = config('evaluation.attempts_rate_limit');

        // Create max attempts in the last hour
        for ($i = 0; $i < $limit; $i++) {
            TopicAttempt::create([
                'user_id' => $this->verifiedUser->id,
                'topic_id' => $this->topic->id,
                'answer_text' => str_repeat('Answer text content. ', 5),
                'status' => 'pending',
                'started_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->verifiedUser)
            ->postJson("/api/v1/topics/{$this->topic->slug}/attempts", [
                'answer' => str_repeat('This is a detailed answer about the topic. ', 5),
            ]);

        $response->assertStatus(429);
    }
}
