<?php

return [
    'pass_threshold' => (int) env('EVALUATION_PASS_THRESHOLD', 60),
    'max_answer_length' => 5000,
    'min_answer_length' => 50,
    'attempts_rate_limit' => 10, // per hour per user
];
