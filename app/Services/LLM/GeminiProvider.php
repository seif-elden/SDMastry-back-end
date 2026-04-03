<?php

namespace App\Services\LLM;

use App\Contracts\LLMProviderInterface;
use App\Exceptions\LLMException;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements LLMProviderInterface
{
    public function __construct(private readonly string $apiKey) {}

    public function chat(string $systemPrompt, array $messages): string
    {
        $userContent = implode("\n\n", array_map(
            fn (array $message) => strtoupper($message['role'] ?? 'user') . ': ' . ($message['content'] ?? ''),
            $messages,
        ));

        $response = Http::timeout((int) config('evaluation.gemini_timeout', 60))
            ->acceptJson()
            ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=' . urlencode($this->apiKey), [
                'contents' => [[
                    'parts' => [[
                        'text' => $systemPrompt . "\n\n" . $userContent,
                    ]],
                ]],
            ]);

        if (! $response->successful()) {
            throw new LLMException('Gemini chat failed: ' . $response->body());
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($content) || trim($content) === '') {
            throw new LLMException('Gemini response did not contain valid message content.');
        }

        return $content;
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }
}
