<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_valid_link(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->postJson("/api/v1/auth/email/verify/{$user->id}/{$hash}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Email verified successfully.',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_fails_with_invalid_hash(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->postJson("/api/v1/auth/email/verify/{$user->id}/invalid-hash");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification link.',
            ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_is_throttled(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // First two should succeed
        for ($i = 0; $i < 2; $i++) {
            $this->withToken($token)
                ->postJson('/api/v1/auth/email/resend')
                ->assertOk();
        }

        // Third should be throttled
        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend');

        $response->assertStatus(429);
    }
}
