<?php

namespace App\Services\Evaluation;

class EvaluationPrompts
{
    public static function evaluatorSystemPrompt(): string
    {
        return 'You are an expert software engineering interviewer evaluating a candidate\'s answer. '
            . 'You assess technical accuracy, depth of understanding, and practical application. '
            . 'You respond ONLY with valid JSON.';
    }

    public static function evaluatorUserPrompt(string $question, string $answer, string $context): string
    {
        return "Topic: {topic_title}\n"
            . "Hook Question: {$question}\n"
            . "Reference Material: {$context}\n"
            . "Candidate's Answer: {$answer}\n\n"
            . "Evaluate this answer and respond with ONLY this JSON structure:\n"
            . "{\n"
            . "  \"score\": <0-100 integer>,\n"
            . "  \"key_strengths\": [\"strength1\", \"strength2\"],\n"
            . "  \"key_weaknesses\": [\"weakness1\", \"weakness2\"],\n"
            . "  \"concepts_to_study\": [\"concept1\", \"concept2\"],\n"
            . "  \"brief_assessment\": \"2-3 sentence technical assessment\"\n"
            . "}";
    }

    /**
     * @param  array<string, mixed>  $evalA
     * @param  array<string, mixed>  $evalB
     */
    public static function synthesizerPrompt(array $evalA, array $evalB, string $question, string $ragContext): string
    {
        return "You received two independent evaluations of a student's software engineering answer.\n"
            . "Merge them into one final structured feedback with a model answer.\n"
            . "Question: {$question}\n"
            . "Reference Material: {$ragContext}\n"
            . 'Evaluation A: ' . json_encode($evalA, JSON_UNESCAPED_SLASHES) . "\n"
            . 'Evaluation B: ' . json_encode($evalB, JSON_UNESCAPED_SLASHES) . "\n\n"
            . "Respond ONLY with valid JSON:\n"
            . "{\n"
            . "  \"score\": <average of both scores>,\n"
            . "  \"passed\": <true if score >= 80>,\n"
            . "  \"key_strengths\": [...merged unique strengths],\n"
            . "  \"key_weaknesses\": [...merged unique weaknesses],\n"
            . "  \"concepts_to_study\": [...merged unique concepts],\n"
            . "  \"prompt_to_explain\": \"Ask me to explain [most important weak concept] in detail\",\n"
            . "  \"prompt_to_next\": \"Ready for [suggest next logical topic]? Let's try it.\",\n"
            . "  \"model_answer\": \"Comprehensive 200-400 word model answer grounded in the reference material\",\n"
            . "  \"rag_sources\": [{\"book\": \"\", \"relevance\": \"\"}]\n"
            . "}";
    }
}
