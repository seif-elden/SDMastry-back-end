<?php

namespace Tests\Unit\Services\Evaluation;

use App\Contracts\LLMProviderInterface;
use App\DTO\RagContext;
use App\Exceptions\LLMException;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Services\Evaluation\AttemptEvaluationService;
use App\Services\LLM\LLMProviderFactory;
use App\Services\RAG\RagRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AttemptEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RagRetrievalService&MockInterface $ragRetrievalService;

    private LLMProviderFactory&MockInterface $providerFactory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'evaluation.ollama_agent1_model' => 'agent-1',
            'evaluation.ollama_agent2_model' => 'agent-2',
            'evaluation.ollama_synthesizer_model' => 'synthesizer',
            'evaluation.pass_threshold' => 80,
        ]);

        $this->ragRetrievalService = Mockery::mock(RagRetrievalService::class);
        $this->providerFactory = Mockery::mock(LLMProviderFactory::class);
    }

    public function test_happy_path_returns_valid_evaluation_result(): void
    {
        $attempt = $this->makeAttempt();

        $this->ragRetrievalService->shouldReceive('retrieve')->once()->andReturn(
            new RagContext(
                bookChunks: [['text' => 'CAP theorem details', 'book' => 'DDIA', 'chapter' => '9', 'relevance_score' => 0.92]],
                modelAnswers: [],
                combinedContext: 'CAP theorem details',
            )
        );

        $providerA = Mockery::mock(LLMProviderInterface::class);
        $providerB = Mockery::mock(LLMProviderInterface::class);
        $providerS = Mockery::mock(LLMProviderInterface::class);

        $providerA->shouldReceive('chat')->once()->andReturn(json_encode([
            'score' => 84,
            'key_strengths' => ['Strong trade-off analysis'],
            'key_weaknesses' => ['Could add failure mode example'],
            'concepts_to_study' => ['Consistency models'],
            'brief_assessment' => 'Good technical grounding.',
        ], JSON_THROW_ON_ERROR));

        $providerB->shouldReceive('chat')->once()->andReturn(json_encode([
            'score' => 86,
            'key_strengths' => ['Accurate terminology'],
            'key_weaknesses' => ['Needs deeper partition example'],
            'concepts_to_study' => ['Replication lag'],
            'brief_assessment' => 'Solid answer with room to deepen examples.',
        ], JSON_THROW_ON_ERROR));

        $providerS->shouldReceive('chat')->once()->andReturn(json_encode([
            'score' => 85,
            'passed' => true,
            'key_strengths' => ['Strong trade-off analysis', 'Accurate terminology'],
            'key_weaknesses' => ['Needs deeper examples'],
            'concepts_to_study' => ['Consistency models'],
            'brief_assessment' => 'Technically sound with moderate gaps.',
            'prompt_to_explain' => 'Ask me to explain consistency models in detail',
            'prompt_to_next' => "Ready for quorum systems? Let's try it.",
            'model_answer' => 'A correct CAP answer explains consistency, availability, and partition tolerance trade-offs.',
            'rag_sources' => [['book' => 'DDIA', 'relevance' => '92%']],
        ], JSON_THROW_ON_ERROR));

        $this->providerFactory->shouldReceive('makeEvaluator')->with('agent-1')->once()->andReturn($providerA);
        $this->providerFactory->shouldReceive('makeEvaluator')->with('agent-2')->once()->andReturn($providerB);
        $this->providerFactory->shouldReceive('makeEvaluator')->with('synthesizer')->once()->andReturn($providerS);

        $service = new AttemptEvaluationService($this->ragRetrievalService, $this->providerFactory);

        $result = $service->evaluate($attempt);

        $this->assertSame(85, $result->score);
        $this->assertTrue($result->passed);
        $this->assertNotEmpty($result->modelAnswer);
    }

    public function test_malformed_evaluator_json_retries_once_then_uses_fallback(): void
    {
        $attempt = $this->makeAttempt();

        $this->ragRetrievalService->shouldReceive('retrieve')->once()->andReturn(
            new RagContext(
                bookChunks: [['text' => 'Ref', 'book' => 'DDIA', 'chapter' => '9', 'relevance_score' => 0.5]],
                modelAnswers: [],
                combinedContext: 'Ref',
            )
        );

        $providerA = Mockery::mock(LLMProviderInterface::class);
        $providerB = Mockery::mock(LLMProviderInterface::class);
        $providerS = Mockery::mock(LLMProviderInterface::class);

        $providerA->shouldReceive('chat')->twice()->andReturn('not-json');

        $providerB->shouldReceive('chat')->once()->andReturn(json_encode([
            'score' => 90,
            'key_strengths' => ['Good explanation'],
            'key_weaknesses' => ['Minor omission'],
            'concepts_to_study' => ['Leader election'],
            'brief_assessment' => 'Strong answer.',
        ], JSON_THROW_ON_ERROR));

        $providerS->shouldReceive('chat')->once()->andReturn(json_encode([
            'score' => 70,
            'passed' => false,
            'key_strengths' => ['Good explanation'],
            'key_weaknesses' => ['Evaluation response was malformed.'],
            'concepts_to_study' => ['Leader election'],
            'brief_assessment' => 'One evaluator returned malformed JSON and fallback was used.',
            'prompt_to_explain' => 'Ask me to explain leader election in detail',
            'prompt_to_next' => "Ready for consensus basics? Let's try it.",
            'model_answer' => 'Model answer text.',
            'rag_sources' => [['book' => 'DDIA', 'relevance' => '50%']],
        ], JSON_THROW_ON_ERROR));

        $this->providerFactory->shouldReceive('makeEvaluator')->with('agent-1')->once()->andReturn($providerA);
        $this->providerFactory->shouldReceive('makeEvaluator')->with('agent-2')->once()->andReturn($providerB);
        $this->providerFactory->shouldReceive('makeEvaluator')->with('synthesizer')->once()->andReturn($providerS);

        $service = new AttemptEvaluationService($this->ragRetrievalService, $this->providerFactory);
        $result = $service->evaluate($attempt);

        $this->assertSame(70, $result->score);
        $this->assertContains('Evaluation response was malformed.', $result->keyWeaknesses);
    }

    public function test_evaluator_timeout_throws_exception(): void
    {
        $attempt = $this->makeAttempt();

        $this->ragRetrievalService->shouldReceive('retrieve')->once()->andReturn(
            new RagContext(
                bookChunks: [],
                modelAnswers: [],
                combinedContext: 'Ref',
            )
        );

        $providerA = Mockery::mock(LLMProviderInterface::class);
        $providerA->shouldReceive('chat')->once()->andThrow(new LLMException('timeout'));

        $this->providerFactory->shouldReceive('makeEvaluator')->with('agent-1')->once()->andReturn($providerA);

        $service = new AttemptEvaluationService($this->ragRetrievalService, $this->providerFactory);

        $this->expectException(LLMException::class);
        $service->evaluate($attempt);
    }

    private function makeAttempt(): TopicAttempt
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $topic = Topic::create([
            'slug' => 'cap-theorem',
            'title' => 'CAP Theorem',
            'category' => 'distributed-systems',
            'section' => 'core',
            'level' => 'intermediate',
            'hook_question' => 'Explain CAP trade-offs.',
            'description' => 'Trade-offs in distributed systems',
            'key_points' => ['consistency', 'availability', 'partition tolerance'],
            'sort_order' => 1,
        ]);

        return TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => 'My answer about CAP trade-offs.',
            'status' => 'pending',
            'started_at' => now(),
        ]);
    }
}
