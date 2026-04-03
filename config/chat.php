<?php

return [
    'message_min_length' => 1,
    'message_max_length' => 2000,
    'history_limit' => 20,
    'rate_limit_per_minute' => 20,
    'original_answer_excerpt_length' => 200,

    'supported_agents' => [
        'ollama',
        'openai',
        'gemini',
        'grok',
    ],

    'api_key_providers' => [
        'openai',
        'gemini',
        'grok',
    ],

    'transient_error_message' => "I'm having trouble thinking right now. Please try again in a moment.",
    'off_topic_redirect_template' => "I'm here to help you master %s. Let's stay focused - what part would you like me to explain?",
];
