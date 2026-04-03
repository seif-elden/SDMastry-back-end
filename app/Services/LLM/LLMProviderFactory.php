<?php

namespace App\Services\LLM;

use App\Contracts\LLMProviderInterface;
use App\Models\User;

class LLMProviderFactory
{
    public function make(User $user): LLMProviderInterface
    {
        $selectedAgent = strtolower($user->selected_agent ?? 'ollama');

        return match ($selectedAgent) {
            'openai' => $this->resolveWithFallback(
                $this->makeFromUserKey($user, 'openai', fn (string $key) => new OpenAIProvider($key)),
            ),
            'gemini' => $this->resolveWithFallback(
                $this->makeFromUserKey($user, 'gemini', fn (string $key) => new GeminiProvider($key)),
            ),
            'grok' => $this->resolveWithFallback(
                $this->makeFromUserKey($user, 'grok', fn (string $key) => new GrokProvider($key)),
            ),
            default => $this->fallbackOllama(),
        };
    }

    public function makeEvaluator(string $model): LLMProviderInterface
    {
        return new OllamaProvider($model);
    }

    private function resolveWithFallback(?LLMProviderInterface $provider): LLMProviderInterface
    {
        if ($provider === null || ! $provider->isAvailable()) {
            return $this->fallbackOllama();
        }

        return $provider;
    }

    private function fallbackOllama(): LLMProviderInterface
    {
        return new OllamaProvider(config('evaluation.ollama_synthesizer_model'));
    }

    /**
     * @param  callable(string): LLMProviderInterface  $resolver
     */
    private function makeFromUserKey(User $user, string $provider, callable $resolver): ?LLMProviderInterface
    {
        $encryptedKey = $user->apiKeys()->where('provider', $provider)->value('encrypted_key');

        if (! is_string($encryptedKey) || trim($encryptedKey) === '') {
            return null;
        }

        return $resolver($encryptedKey);
    }
}
