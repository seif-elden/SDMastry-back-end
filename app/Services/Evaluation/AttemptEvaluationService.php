<?php

namespace App\Services\Evaluation;

use App\Contracts\LLMProviderInterface;
use App\DTO\EvaluationResult;
use App\Models\TopicAttempt;
use App\Services\LLM\LLMProviderFactory;
use App\Services\RAG\RagRetrievalService;

class AttemptEvaluationService
{
    public function __construct(
        private readonly RagRetrievalService $ragRetrievalService,
        private readonly LLMProviderFactory $providerFactory,
    ) {}

    public function evaluate(TopicAttempt $attempt): EvaluationResult
    {
        $attempt->loadMissing('topic');

        $ragContext = $this->ragRetrievalService->retrieve(
            $attempt->answer_text,
            $attempt->topic->title,
            $attempt->topic->description,
        );

        $evaluatorPrompt = str_replace(
            '{topic_title}',
            $attempt->topic->title,
            EvaluationPrompts::evaluatorUserPrompt(
                $attempt->topic->hook_question,
                $attempt->answer_text,
                $ragContext->combinedContext,
            ),
        );

        $evalA = $this->evaluateWithModel(config('evaluation.ollama_agent1_model'), $evaluatorPrompt);
        $evalB = $this->evaluateWithModel(config('evaluation.ollama_agent2_model'), $evaluatorPrompt);

        $synthesizerPrompt = EvaluationPrompts::synthesizerPrompt(
            $evalA,
            $evalB,
            $attempt->topic->hook_question,
            $ragContext->combinedContext,
        );

        $synthesizerProvider = $this->providerFactory->makeEvaluator(config('evaluation.ollama_synthesizer_model'));
        $final = $this->parseFinalEvaluation(
            $synthesizerProvider->chat(EvaluationPrompts::evaluatorSystemPrompt(), [
                ['role' => 'user', 'content' => $synthesizerPrompt],
            ]),
            $evalA,
            $evalB,
            $attempt,
            $ragContext->bookChunks,
            $synthesizerProvider,
        );

        return new EvaluationResult(
            score: $final['score'],
            passed: $final['passed'],
            keyStrengths: $final['key_strengths'],
            keyWeaknesses: $final['key_weaknesses'],
            conceptsToStudy: $final['concepts_to_study'],
            briefAssessment: $final['brief_assessment'],
            promptToExplain: $final['prompt_to_explain'],
            promptToNext: $final['prompt_to_next'],
            notes: $final['notes'],
            ragSources: $final['rag_sources'],
            rawEvaluation: $final,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateWithModel(string $model, string $userPrompt): array
    {
        $provider = $this->providerFactory->makeEvaluator($model);

        return $this->parseEvaluatorJson(
            $provider->chat(EvaluationPrompts::evaluatorSystemPrompt(), [
                ['role' => 'user', 'content' => $userPrompt],
            ]),
            fn () => $provider->chat(EvaluationPrompts::evaluatorSystemPrompt(), [
                ['role' => 'user', 'content' => $userPrompt],
            ]),
        );
    }

    /**
     * @param  callable(): string  $retry
     * @return array{score: int, key_strengths: array<int, string>, key_weaknesses: array<int, string>, concepts_to_study: array<int, string>, brief_assessment: string}
     */
    private function parseEvaluatorJson(string $raw, callable $retry): array
    {
        $parsed = $this->decodeJson($raw);

        if ($this->isValidEvaluatorJson($parsed)) {
            return $this->normalizeEvaluatorJson($parsed);
        }

        $retryParsed = $this->decodeJson($retry());

        if ($this->isValidEvaluatorJson($retryParsed)) {
            return $this->normalizeEvaluatorJson($retryParsed);
        }

        return [
            'score' => 50,
            'key_strengths' => ['Attempted to answer the question.'],
            'key_weaknesses' => ['Evaluation response was malformed.'],
            'concepts_to_study' => ['Core concepts from the topic reference material'],
            'brief_assessment' => 'The evaluator returned malformed JSON twice. Review the reference material and provide a more structured answer.',
        ];
    }

    /**
     * @param  array<string, mixed>  $evalA
     * @param  array<string, mixed>  $evalB
     * @param  array<int, array{text: string, book: string, chapter: string, relevance_score: float}>  $bookChunks
     * @return array<string, mixed>
     */
    private function parseFinalEvaluation(
        string $raw,
        array $evalA,
        array $evalB,
        TopicAttempt $attempt,
        array $bookChunks,
        LLMProviderInterface $provider,
    ): array {
        $parsed = $this->decodeJson($raw);

        if ($this->isValidFinalJson($parsed)) {
            return $this->normalizeFinalJson($parsed, $attempt, $provider, $bookChunks);
        }

        $retryPrompt = EvaluationPrompts::synthesizerPrompt(
            $evalA,
            $evalB,
            $attempt->topic->hook_question,
            'Retry: return strict JSON only.',
        );

        $retryParsed = $this->decodeJson(
            $provider->chat(EvaluationPrompts::evaluatorSystemPrompt(), [
                ['role' => 'user', 'content' => $retryPrompt],
            ]),
        );

        if ($this->isValidFinalJson($retryParsed)) {
            return $this->normalizeFinalJson($retryParsed, $attempt, $provider, $bookChunks);
        }

        return $this->fallbackFinalJson($evalA, $evalB, $bookChunks, $attempt, $provider);
    }

    /**
     * @param  mixed  $parsed
     */
    private function isValidEvaluatorJson(mixed $parsed): bool
    {
        return is_array($parsed)
            && isset($parsed['score'], $parsed['key_strengths'], $parsed['key_weaknesses'], $parsed['concepts_to_study'], $parsed['brief_assessment'])
            && is_numeric($parsed['score'])
            && is_array($parsed['key_strengths'])
            && is_array($parsed['key_weaknesses'])
            && is_array($parsed['concepts_to_study'])
            && is_string($parsed['brief_assessment']);
    }

    /**
     * @param  mixed  $parsed
     */
    private function isValidFinalJson(mixed $parsed): bool
    {
        $notes = is_array($parsed)
            ? (string) ($parsed['notes'] ?? $parsed['model_answer'] ?? '')
            : '';

        return is_array($parsed)
            && isset(
                $parsed['score'],
                $parsed['passed'],
                $parsed['key_strengths'],
                $parsed['key_weaknesses'],
                $parsed['concepts_to_study'],
                $parsed['prompt_to_explain'],
                $parsed['prompt_to_next'],
                $parsed['rag_sources'],
            )
            && is_numeric($parsed['score'])
            && is_bool($parsed['passed'])
            && is_array($parsed['key_strengths'])
            && is_array($parsed['key_weaknesses'])
            && is_array($parsed['concepts_to_study'])
            && is_string($parsed['prompt_to_explain'])
            && is_string($parsed['prompt_to_next'])
            && $notes !== ''
            && ! $this->isWeakNotes($notes)
            && ! $this->isPlaceholderNotes($notes)
            && is_array($parsed['rag_sources']);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{score: int, key_strengths: array<int, string>, key_weaknesses: array<int, string>, concepts_to_study: array<int, string>, brief_assessment: string}
     */
    private function normalizeEvaluatorJson(array $parsed): array
    {
        return [
            'score' => max(0, min(100, (int) $parsed['score'])),
            'key_strengths' => $this->normalizeStringList($parsed['key_strengths']),
            'key_weaknesses' => $this->normalizeStringList($parsed['key_weaknesses']),
            'concepts_to_study' => $this->normalizeStringList($parsed['concepts_to_study']),
            'brief_assessment' => trim((string) $parsed['brief_assessment']),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalizeFinalJson(
        array $parsed,
        TopicAttempt $attempt,
        LLMProviderInterface $provider,
        array $bookChunks,
    ): array
    {
        $score = max(0, min(100, (int) $parsed['score']));
        $notes = trim((string) ($parsed['notes'] ?? $parsed['model_answer'] ?? ''));

        if ($this->isWeakNotes($notes)) {
            $notes = $this->generateCanonicalNotes($attempt, $provider, $bookChunks);
        }

        return [
            'score' => $score,
            'passed' => $score >= (int) config('evaluation.pass_threshold', 80),
            'key_strengths' => $this->normalizeStringList($parsed['key_strengths']),
            'key_weaknesses' => $this->normalizeStringList($parsed['key_weaknesses']),
            'concepts_to_study' => $this->normalizeStringList($parsed['concepts_to_study']),
            'brief_assessment' => trim((string) ($parsed['brief_assessment'] ?? 'See key strengths and weaknesses.')),
            'prompt_to_explain' => trim((string) $parsed['prompt_to_explain']),
            'prompt_to_next' => trim((string) $parsed['prompt_to_next']),
            'notes' => $notes,
            'rag_sources' => $this->normalizeRagSources($parsed['rag_sources']),
        ];
    }

    /**
     * @param  array<string, mixed>  $evalA
     * @param  array<string, mixed>  $evalB
     * @param  array<int, array{text: string, book: string, chapter: string, relevance_score: float}>  $bookChunks
     * @return array<string, mixed>
     */
    private function fallbackFinalJson(
        array $evalA,
        array $evalB,
        array $bookChunks,
        TopicAttempt $attempt,
        LLMProviderInterface $provider,
    ): array
    {
        $score = (int) round(((int) $evalA['score'] + (int) $evalB['score']) / 2);
        $weakConcept = $evalA['concepts_to_study'][0] ?? $evalB['concepts_to_study'][0] ?? 'distributed systems fundamentals';

        return [
            'score' => $score,
            'passed' => $score >= (int) config('evaluation.pass_threshold', 80),
            'key_strengths' => array_values(array_unique(array_merge($evalA['key_strengths'], $evalB['key_strengths']))),
            'key_weaknesses' => array_values(array_unique(array_merge($evalA['key_weaknesses'], $evalB['key_weaknesses']))),
            'concepts_to_study' => array_values(array_unique(array_merge($evalA['concepts_to_study'], $evalB['concepts_to_study']))),
            'brief_assessment' => 'The synthesizer returned malformed JSON. This fallback combines both evaluator outputs.',
            'prompt_to_explain' => 'Ask me to explain ' . $weakConcept . ' in detail',
            'prompt_to_next' => "Ready for the next advanced topic? Let's try it.",
            'notes' => $this->generateCanonicalNotes($attempt, $provider, $bookChunks),
            'rag_sources' => $this->fallbackRagSources($bookChunks),
        ];
    }

    /**
     * @return array<int, array{book: string, relevance: string}>
     */
    private function fallbackRagSources(array $bookChunks): array
    {
        $sources = [];

        foreach (array_slice($bookChunks, 0, 3) as $chunk) {
            $sources[] = [
                'book' => (string) ($chunk['book'] ?? 'unknown'),
                'relevance' => (string) number_format(((float) ($chunk['relevance_score'] ?? 0.0)) * 100, 1) . '%',
            ];
        }

        if (empty($sources)) {
            $sources[] = [
                'book' => 'reference material unavailable',
                'relevance' => '0%',
            ];
        }

        return $sources;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item) => is_scalar($item) ? trim((string) $item) : '',
            $value,
        )));
    }

    /**
     * @param  mixed  $value
     * @return array<int, array{book: string, relevance: string}>
     */
    private function normalizeRagSources(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $source) {
            if (! is_array($source)) {
                continue;
            }

            $normalized[] = [
                'book' => trim((string) ($source['book'] ?? 'unknown')),
                'relevance' => trim((string) ($source['relevance'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function isPlaceholderNotes(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return $normalized === 'comprehensive 200-400 word notes grounded in the reference material'
            || $normalized === 'comprehensive 200-400 word model answer grounded in the reference material'
            || str_contains($normalized, 'do not output instructions or placeholders')
            || str_starts_with($normalized, 'write actual 220-420 word notes')
            || str_starts_with($normalized, 'write an actual 200-400 word model answer');
    }

    private function isWeakNotes(string $value): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return true;
        }

        if (mb_strlen($normalized) < 450) {
            return true;
        }

        return str_contains($normalized, 'you should')
            || str_contains($normalized, 'you can')
            || str_contains($normalized, 'consider ')
            || str_contains($normalized, 'try to');
    }

    /**
     * @param  array<int, array{text: string, book: string, chapter: string, relevance_score: float}>  $bookChunks
     */
    private function generateCanonicalNotes(
        TopicAttempt $attempt,
        LLMProviderInterface $provider,
        array $bookChunks,
    ): string {
        $contextPieces = array_map(
            fn (array $chunk) => sprintf('[%s] %s', (string) ($chunk['book'] ?? 'reference'), (string) ($chunk['text'] ?? '')),
            array_slice($bookChunks, 0, 5),
        );

        $raw = $provider->chat(EvaluationPrompts::evaluatorSystemPrompt(), [
            [
                'role' => 'user',
                'content' => EvaluationPrompts::canonicalModelAnswerPrompt(
                    $attempt->topic->hook_question,
                    implode("\n\n", $contextPieces),
                ),
            ],
        ]);

        $candidate = trim($raw);

        if ($this->isWeakNotes($candidate) || $this->isPlaceholderNotes($candidate)) {
            return 'A complete high-scoring answer states the core principle, explains trade-offs with concrete engineering consequences, and maps those trade-offs to practical implementation decisions under realistic constraints.';
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $raw): ?array
    {
        $decoded = json_decode(trim($raw), true);

        return is_array($decoded) ? $decoded : null;
    }
}
