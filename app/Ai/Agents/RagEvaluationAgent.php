<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class RagEvaluationAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** Explain how the model must judge an answer. */
    public function instructions(): string
    {
        return config('rag-evaluation.judge.instructions');
    }

    /** Define the reliable structure returned by the judge. */
    public function schema(JsonSchema $schema): array
    {
        return [
            'correctness' => $schema->integer()->min(1)->max(5)->required(),
            'faithfulness' => $schema->integer()->min(1)->max(5)->required(),
            'relevance' => $schema->integer()->min(1)->max(5)->required(),
            'completeness' => $schema->integer()->min(1)->max(5)->required(),
            'reason' => $schema->string()->required(),
        ];
    }

    /** Keep the local judge deterministic and disable reasoning output. */
    public function providerOptions(Lab|string $provider): array
    {
        return $provider === Lab::Ollama
            ? ['temperature' => 0, 'think' => false]
            : [];
    }
}
