<?php

namespace App\Services\Rag;

use App\Ai\Agents\RagAgent;
use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

class RagService
{
    public function __construct(
        private readonly DocumentRetriever $retriever,
    ) {}

    /** Answer a question using the retrieved document context. */
    public function answer(string $question): string
    {
        Log::info('RAG question received.', [
            'question' => $question,
        ]);

        $chunks = $this->retriever->retrieve($question);

        return $this->answerFromChunks($question, $chunks);
    }

    /** Answer using chunks that have already been retrieved. */
    public function answerFromChunks(string $question, Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            Log::info('RAG answer skipped because no context was found.');

            return config('rag.agent.no_context_answer');
        }

        $prompt = $this->buildPrompt($question, $chunks);

        Log::info('RAG prompt prepared.', [
            'prompt' => $prompt,
        ]);

        $response = RagAgent::make()->prompt(
            $prompt,
            provider: Lab::Ollama,
            model: config('ai.providers.ollama.models.text.default'),
            timeout: config('rag.timeout'),
        );

        Log::info('RAG answer generated.', [
            'answer' => $response->text,
        ]);

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
