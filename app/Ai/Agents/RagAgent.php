<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class RagAgent implements Agent, HasProviderOptions
{
    use Promptable;

    /** Define how the RAG agent should answer. */
    public function instructions(): string
    {
        return config('rag.agent.instructions');
    }

    /** Keep answers short and focused. */
    public function maxTokens(): int
    {
        return config('rag.agent.max_tokens');
    }

    /** Disable reasoning output for simple document questions. */
    public function providerOptions(Lab|string $provider): array
    {
        return $provider === Lab::Ollama ? ['think' => false] : [];
    }
}
