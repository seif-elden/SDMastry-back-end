<?php

namespace Tests\Unit\Services\LLM;

use App\Exceptions\LLMException;
use App\Services\LLM\OllamaProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rag.ollama_base_url' => 'http://localhost:11434',
            'evaluation.ollama_chat_timeout' => 30,
            'evaluation.max_retries' => 3,
        ]);
    }

    public function test_chat_sends_correct_payload(): void
    {
        Http::fake([
            'localhost:11434/api/chat' => Http::response([
                'message' => [
                    'content' => '{"score":80}',
                ],
            ]),
        ]);

        $provider = new OllamaProvider('llama3:latest');

        $response = $provider->chat('system prompt', [
            ['role' => 'user', 'content' => 'answer to evaluate'],
        ]);

        $this->assertEquals('{"score":80}', $response);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/chat'
                && $request['model'] === 'llama3:latest'
                && $request['stream'] === false
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][0]['content'] === 'system prompt'
                && $request['messages'][1]['role'] === 'user';
        });
    }

    public function test_chat_retries_on_timeout_then_succeeds(): void
    {
        Http::fakeSequence()
            ->push(fn () => throw new ConnectionException('timeout'))
            ->push(fn () => throw new ConnectionException('timeout'))
            ->push([
                'message' => [
                    'content' => '{"score":70}',
                ],
            ], 200);

        $provider = new OllamaProvider('llama3:latest');

        $response = $provider->chat('system prompt', [
            ['role' => 'user', 'content' => 'answer'],
        ]);

        $this->assertEquals('{"score":70}', $response);
        Http::assertSentCount(3);
    }

    public function test_chat_throws_on_malformed_json_response_shape(): void
    {
        Http::fake([
            'localhost:11434/api/chat' => Http::response([
                'unexpected' => 'shape',
            ], 200),
        ]);

        $provider = new OllamaProvider('llama3:latest');

        $this->expectException(LLMException::class);
        $provider->chat('system prompt', [
            ['role' => 'user', 'content' => 'answer'],
        ]);
    }
}
