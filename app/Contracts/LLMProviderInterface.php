<?php

namespace App\Contracts;

interface LLMProviderInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(string $systemPrompt, array $messages): string;

    public function isAvailable(): bool;

    public function getProviderName(): string;
}
