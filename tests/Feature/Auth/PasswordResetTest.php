<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_email(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'If that email exists, a reset link has been sent.',
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $token = 'valid-reset-token';

        DB::table('password_reset_tokens')->insert([
            'email' => 'john@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully.',
            ]);

        // Verify password was changed
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_password_fails_with_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $token = 'expired-token';

        DB::table('password_reset_tokens')->insert([
            'email' => 'john@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Reset token has expired.',
            ]);
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $token = 'single-use-token';

        DB::table('password_reset_tokens')->insert([
            'email' => 'john@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // First reset should succeed
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        // Second reset with same token should fail
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'anotherpassword',
            'password_confirmation' => 'anotherpassword',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid reset token.',
            ]);
    }
}
