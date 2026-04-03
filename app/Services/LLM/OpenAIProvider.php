<?php

namespace App\Services\LLM;

use App\Contracts\LLMProviderInterface;
use App\Exceptions\LLMException;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements LLMProviderInterface
{
    public function __construct(private readonly string $apiKey) {}

    public function chat(string $systemPrompt, array $messages): string
    {
        $response = Http::timeout((int) config('evaluation.openai_timeout', 60))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => array_merge([
                    ['role' => 'system', 'content' => $systemPrompt],
                ], $messages),
            ]);

        if (! $response->successful()) {
            throw new LLMException('OpenAI chat failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new LLMException('OpenAI response did not contain valid message content.');
        }

        return $content;
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
