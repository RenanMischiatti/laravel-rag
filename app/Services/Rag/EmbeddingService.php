<?php

namespace App\Services\Rag;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class EmbeddingService
{
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
}
