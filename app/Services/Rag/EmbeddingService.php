<?php

namespace App\Services\Rag;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class EmbeddingService
{
    /** Embed document chunks in a single request. */
    public function embedMany(array $chunks): array
    {
        if ($chunks === []) {
            return [];
        }

        $inputs = array_map(
            fn (string $chunk): string => 'search_document: '.$chunk,
            $chunks,
        );

        return Embeddings::for($inputs)
            ->dimensions(768)
            ->timeout(120)
            ->generate(Lab::Ollama, config('ai.providers.ollama.models.embeddings.default'))
            ->embeddings;
    }

    /** Embed a search query. */
    public function embedQuery(string $question): array
    {
        return Embeddings::for(['search_query: '.$question])
            ->dimensions(768)
            ->timeout(120)
            ->generate(Lab::Ollama, config('ai.providers.ollama.models.embeddings.default'))
            ->first();
    }
}
