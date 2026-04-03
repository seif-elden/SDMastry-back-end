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
            . "Scoring calibration rules:\n"
            . "- Use the full 0-100 range and avoid clustering around 70.\n"
            . "- 85-95: technically correct, deep, and practical even if minor omissions exist.\n"
            . "- 96-100: complete, precise, production-aware answer with strong trade-off reasoning.\n"
            . "- Do not deduct heavily for wording style if technical reasoning is correct.\n\n"
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
            . "Merge them into one final structured feedback with notes.\n"
            . "Question: {$question}\n"
            . "Reference Material: {$ragContext}\n"
            . 'Evaluation A: ' . json_encode($evalA, JSON_UNESCAPED_SLASHES) . "\n"
            . 'Evaluation B: ' . json_encode($evalB, JSON_UNESCAPED_SLASHES) . "\n\n"
            . "Notes requirements:\n"
            . "- It must be a canonical top-tier answer that deserves 100/100.\n"
            . "- It must be direct answer content, not coaching, advice, or suggestions.\n"
            . "- Do not use phrases like 'you should', 'consider', 'try to', or instructions to the learner.\n"
            . "- Write 220-420 words with concrete technical details and trade-offs.\n"
            . "- If a learner copies these notes verbatim, it should score 100.\n\n"
            . "Respond ONLY with valid JSON:\n"
            . "{\n"
            . "  \"score\": <average of both scores>,\n"
            . "  \"passed\": <true if score >= 80>,\n"
            . "  \"key_strengths\": [...merged unique strengths],\n"
            . "  \"key_weaknesses\": [...merged unique weaknesses],\n"
            . "  \"concepts_to_study\": [...merged unique concepts],\n"
            . "  \"prompt_to_explain\": \"Ask me to explain [most important weak concept] in detail\",\n"
            . "  \"prompt_to_next\": \"Ready for [suggest next logical topic]? Let's try it.\",\n"
            . "  \"notes\": \"Concrete final answer text only (220-420 words), suitable for a 100/100 score.\",\n"
            . "  \"rag_sources\": [{\"book\": \"\", \"relevance\": \"\"}]\n"
            . "}\n\n"
            . "Important: Return concrete content for notes. Never return meta text like 'write notes' or placeholders.";
    }

    public static function canonicalModelAnswerPrompt(string $question, string $ragContext): string
    {
        return "Write the best possible answer to this software engineering interview question.\n"
            . "Question: {$question}\n"
            . "Reference Material: {$ragContext}\n\n"
            . "Rules:\n"
            . "- Output only the final answer content, no JSON, no preface, no bullet labels.\n"
            . "- 220-420 words.\n"
            . "- It must be a 100/100 quality answer with concrete trade-offs and implementation detail.\n"
            . "- Do not address the learner directly and do not give suggestions.\n"
            . "- Avoid phrases such as 'you should', 'consider', and 'try to'.";
    }
}
