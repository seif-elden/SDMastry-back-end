<?php

namespace Tests\Unit\Services\Evaluation;

use App\Services\Evaluation\EvaluationPrompts;
use Tests\TestCase;

class EvaluationPromptsTest extends TestCase
{
    public function test_evaluator_prompts_include_required_placeholders_and_json_contract(): void
    {
        $systemPrompt = EvaluationPrompts::evaluatorSystemPrompt();
        $userPrompt = EvaluationPrompts::evaluatorUserPrompt('What is CAP theorem?', 'My answer', 'Reference text');

        $this->assertStringContainsString('respond only with valid json', strtolower($systemPrompt));
        $this->assertStringContainsString('Topic: {topic_title}', $userPrompt);
        $this->assertStringContainsString('Hook Question:', $userPrompt);
        $this->assertStringContainsString('"score": <0-100 integer>', $userPrompt);
        $this->assertStringContainsString('"key_strengths"', $userPrompt);
        $this->assertStringContainsString('"key_weaknesses"', $userPrompt);
        $this->assertStringContainsString('"concepts_to_study"', $userPrompt);
    }

    public function test_synthesizer_prompt_contains_required_response_json_structure(): void
    {
        $prompt = EvaluationPrompts::synthesizerPrompt(
            ['score' => 80, 'key_strengths' => [], 'key_weaknesses' => [], 'concepts_to_study' => [], 'brief_assessment' => 'ok'],
            ['score' => 90, 'key_strengths' => [], 'key_weaknesses' => [], 'concepts_to_study' => [], 'brief_assessment' => 'ok'],
            'What is CAP theorem?',
            'DDIA chapter reference',
        );

        $this->assertStringContainsString('Respond ONLY with valid JSON', $prompt);
        $this->assertStringContainsString('"score": <average of both scores>', $prompt);
        $this->assertStringContainsString('"passed": <true if score >= 80>', $prompt);
        $this->assertStringContainsString('"model_answer"', $prompt);
        $this->assertStringContainsString('"rag_sources"', $prompt);
    }
}
