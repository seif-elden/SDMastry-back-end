<?php

namespace Tests\Unit\Services\LLM;

use App\Services\LLM\GeminiProvider;
use App\Services\LLM\GrokProvider;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\OllamaProvider;
use App\Services\LLM\OpenAIProvider;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMProviderFactoryTest extends TestCase
{
    use RefreshDatabase;

    private LLMProviderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new LLMProviderFactory;
        config(['evaluation.ollama_synthesizer_model' => 'llama3:latest']);
    }

    public function test_resolves_openai_provider_when_agent_and_key_exist(): void
    {
        $user = User::factory()->create(['selected_agent' => 'openai']);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'encrypted_key' => 'sk-test-openai',
        ]);

        $provider = $this->factory->make($user);

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_factory_uses_decrypted_user_api_key_for_openai_calls(): void
    {
        $user = User::factory()->create(['selected_agent' => 'openai']);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'encrypted_key' => 'sk-plain-text-key',
        ]);

        $storedValue = DB::table('user_api_keys')
            ->where('user_id', $user->id)
            ->where('provider', 'openai')
            ->value('encrypted_key');

        $this->assertNotSame('sk-plain-text-key', $storedValue);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
            ], 200),
        ]);

        $provider = $this->factory->make($user);
        $provider->chat('system', [
            ['role' => 'user', 'content' => 'hello'],
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->header('Authorization')[0] === 'Bearer sk-plain-text-key';
        });
    }

    public function test_resolves_gemini_provider_when_agent_and_key_exist(): void
    {
        $user = User::factory()->create(['selected_agent' => 'gemini']);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'encrypted_key' => 'gm-test-key',
        ]);

        $provider = $this->factory->make($user);

        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    public function test_falls_back_to_ollama_when_key_missing(): void
    {
        $user = User::factory()->create(['selected_agent' => 'openai']);

        $provider = $this->factory->make($user);

        $this->assertInstanceOf(OllamaProvider::class, $provider);
        $this->assertSame('ollama', $provider->getProviderName());
    }

    public function test_resolves_grok_provider_when_agent_and_key_exist(): void
    {
        $user = User::factory()->create(['selected_agent' => 'grok']);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'grok',
            'encrypted_key' => 'xai-test-key',
        ]);

        $provider = $this->factory->make($user);

        $this->assertInstanceOf(GrokProvider::class, $provider);
    }
}
