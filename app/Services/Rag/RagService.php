<?php

namespace App\Services\Rag;

use App\Ai\Agents\RagAgent;
use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;

class RagService
{
    public function __construct(
        private readonly DocumentRetriever $retriever,
    ) {}

    /** Answer a question using the retrieved document context. */
    public function answer(string $question): string
    {
        $chunks = $this->retriever->retrieve($question);

        if ($chunks->isEmpty()) {
            return config('rag.agent.no_context_answer');
        }

        $response = RagAgent::make()->prompt(
            $this->buildPrompt($question, $chunks),
            provider: Lab::Ollama,
            model: config('ai.providers.ollama.models.text.default'),
            timeout: config('rag.timeout'),
        );

        return $response->text;
    }

    /** Build the question and context sent to the agent. */
    private function buildPrompt(string $question, Collection $chunks): string
    {
        $context = $chunks
            ->map(fn (DocumentChunk $chunk): string => <<<TEXT
                [{$chunk->document->filename} #{$chunk->position}]
                {$chunk->content}
            TEXT)
            ->implode("\n\n");

        return <<<PROMPT
            Context:
            {$context}

            Question: {$question}
        PROMPT;
    }
}
