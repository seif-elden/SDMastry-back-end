<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_settings_returns_providers_without_exposing_keys(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'selected_agent' => 'openai',
        ]);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'encrypted_key' => 'super-secret-key',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/settings/agent');

        $response->assertOk()
            ->assertJsonPath('data.selected_agent', 'openai')
            ->assertJsonPath('data.api_keys.0.provider', 'openai')
            ->assertJsonMissingPath('data.api_keys.0.api_key');
    }

    public function test_update_selected_agent_updates_user_record(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'selected_agent' => 'ollama',
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/settings/agent', [
                'selected_agent' => 'gemini',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.selected_agent', 'gemini');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'selected_agent' => 'gemini',
        ]);
    }

    public function test_store_api_key_encrypts_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plain = 'my-openai-key';

        $response = $this->actingAs($user)
            ->putJson('/api/v1/settings/api-keys/openai', [
                'api_key' => $plain,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.is_set', true);

        $stored = UserApiKey::where('user_id', $user->id)
            ->where('provider', 'openai')
            ->firstOrFail();

        $this->assertNotSame($plain, $stored->getRawOriginal('encrypted_key'));
    }

    public function test_delete_key_resets_agent_to_ollama_if_provider_selected(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'selected_agent' => 'grok',
        ]);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'grok',
            'encrypted_key' => 'some-grok-key',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/settings/api-keys/grok');

        $response->assertOk()
            ->assertJsonPath('data.provider', 'grok')
            ->assertJsonPath('data.is_set', false);

        $this->assertDatabaseMissing('user_api_keys', [
            'user_id' => $user->id,
            'provider' => 'grok',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'selected_agent' => 'ollama',
        ]);
    }
}
