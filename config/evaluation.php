<?php

return [
    'pass_threshold' => (int) env('EVALUATION_PASS_THRESHOLD', 60),
    'max_answer_length' => 5000,
    'min_answer_length' => 50,
    'attempts_rate_limit' => 10, // per hour per user
    'max_retries' => (int) env('EVALUATION_MAX_RETRIES', 3),
    'ollama_chat_timeout' => (int) env('OLLAMA_CHAT_TIMEOUT', 120),
    'ollama_agent1_model' => env('OLLAMA_AGENT1_MODEL', 'llama3:latest'),
    'ollama_agent2_model' => env('OLLAMA_AGENT2_MODEL', 'mistral:latest'),
    'ollama_synthesizer_model' => env('OLLAMA_SYNTHESIZER', 'llama3:latest'),
    'openai_timeout' => (int) env('OPENAI_CHAT_TIMEOUT', 60),
    'gemini_timeout' => (int) env('GEMINI_CHAT_TIMEOUT', 60),
    'grok_timeout' => (int) env('GROK_CHAT_TIMEOUT', 60),
];
