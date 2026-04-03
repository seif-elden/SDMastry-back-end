<?php

namespace App\Services\LLM;

use App\Contracts\LLMProviderInterface;
use App\Exceptions\LLMException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements LLMProviderInterface
{
    public function __construct(
        private readonly string $model,
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeout = null,
    ) {}

    public function chat(string $systemPrompt, array $messages): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_merge([
                ['role' => 'system', 'content' => $systemPrompt],
            ], $messages),
            'stream' => false,
        ];

        $maxRetries = (int) config('evaluation.max_retries', 3);
        $attempt = 0;

        while (true) {
            try {
                $response = Http::timeout($this->timeoutSeconds())
                    ->acceptJson()
                    ->post("{$this->resolvedBaseUrl()}/api/chat", $payload);

                if ($response->status() >= 500 && $attempt < $maxRetries - 1) {
                    sleep((int) pow(2, $attempt + 1));
                    $attempt++;
                    continue;
                }

                if (! $response->successful()) {
                    throw new LLMException('Ollama chat failed: ' . $response->body());
                }

                $content = $response->json('message.content');

                if (! is_string($content) || trim($content) === '') {
                    throw new LLMException('Ollama response did not contain valid message content.');
                }

                return $content;
            } catch (ConnectionException $e) {
                if ($attempt >= $maxRetries - 1) {
                    throw new LLMException('Ollama connection failed after retries.', previous: $e);
                }

                sleep((int) pow(2, $attempt + 1));
                $attempt++;
            }
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->resolvedBaseUrl()}/api/tags");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'ollama';
    }

    private function resolvedBaseUrl(): string
    {
        return rtrim($this->baseUrl ?? config('rag.ollama_base_url'), '/');
    }

    private function timeoutSeconds(): int
    {
        return $this->timeout ?? (int) config('evaluation.ollama_chat_timeout', 120);
    }
}
