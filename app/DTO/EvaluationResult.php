<?php

namespace App\DTO;

class EvaluationResult
{
    /**
     * @param  array<int, string>  $keyStrengths
     * @param  array<int, string>  $keyWeaknesses
     * @param  array<int, string>  $conceptsToStudy
     * @param  array<int, array{book: string, relevance: string}>  $ragSources
     * @param  array<string, mixed>  $rawEvaluation
     */
    public function __construct(
        public readonly int $score,
        public readonly bool $passed,
        public readonly array $keyStrengths,
        public readonly array $keyWeaknesses,
        public readonly array $conceptsToStudy,
        public readonly string $briefAssessment,
        public readonly string $promptToExplain,
        public readonly string $promptToNext,
        public readonly string $modelAnswer,
        public readonly array $ragSources,
        public readonly array $rawEvaluation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'passed' => $this->passed,
            'key_strengths' => $this->keyStrengths,
            'key_weaknesses' => $this->keyWeaknesses,
            'concepts_to_study' => $this->conceptsToStudy,
            'brief_assessment' => $this->briefAssessment,
            'prompt_to_explain' => $this->promptToExplain,
            'prompt_to_next' => $this->promptToNext,
            'model_answer' => $this->modelAnswer,
            'rag_sources' => $this->ragSources,
        ];
    }
}
