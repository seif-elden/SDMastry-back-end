<?php

namespace App\Services\LLM;

use App\Contracts\LLMProviderInterface;
use App\Exceptions\LLMException;
use Illuminate\Support\Facades\Http;

class GrokProvider implements LLMProviderInterface
{
    public function __construct(private readonly string $apiKey) {}

    public function chat(string $systemPrompt, array $messages): string
    {
        $response = Http::timeout((int) config('evaluation.grok_timeout', 60))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post('https://api.x.ai/v1/chat/completions', [
                'model' => 'grok-2',
                'messages' => array_merge([
                    ['role' => 'system', 'content' => $systemPrompt],
                ], $messages),
            ]);

        if (! $response->successful()) {
            throw new LLMException('Grok chat failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new LLMException('Grok response did not contain valid message content.');
        }

        return $content;
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function getProviderName(): string
    {
        return 'grok';
    }
}
