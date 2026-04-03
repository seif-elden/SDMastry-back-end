<?php

namespace App\Services\Settings;

use App\Models\User;
use App\Models\UserApiKey;

class AgentSettingsService
{
    /**
     * @return array{selected_agent: string, api_keys: array<int, array{provider: string, is_set: bool}>}
     */
    public function getSettings(User $user): array
    {
        $storedProviders = $user->apiKeys()
            ->pluck('provider')
            ->all();

        $providers = config('chat.api_key_providers', ['openai', 'gemini', 'grok']);

        return [
            'selected_agent' => (string) ($user->selected_agent ?? 'ollama'),
            'api_keys' => array_map(
                fn (string $provider) => [
                    'provider' => $provider,
                    'is_set' => in_array($provider, $storedProviders, true),
                ],
                $providers,
            ),
        ];
    }

    public function updateSelectedAgent(User $user, string $selectedAgent): User
    {
        $user->update([
            'selected_agent' => $selectedAgent,
        ]);

        return $user->refresh();
    }

    /**
     * @return array{provider: string, is_set: bool}
     */
    public function storeApiKey(User $user, string $provider, string $apiKey): array
    {
        UserApiKey::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            [
                'encrypted_key' => $apiKey,
            ],
        );

        return [
            'provider' => $provider,
            'is_set' => true,
        ];
    }

    public function deleteApiKey(User $user, string $provider): void
    {
        $user->apiKeys()->where('provider', $provider)->delete();

        if ($user->selected_agent === $provider) {
            $user->update([
                'selected_agent' => 'ollama',
            ]);
        }
    }
}
