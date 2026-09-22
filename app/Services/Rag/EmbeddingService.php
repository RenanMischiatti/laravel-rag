<?php

namespace App\Services\Rag;

use InvalidArgumentException;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class EmbeddingService
{
    /** Embed document chunks in a single request. */
    public function embedMany(array $chunks, ?string $profile = null): array
    {
        return $this->embedManyUsing($chunks, $this->profile($profile));
    }

    /** Embed document chunks using a complete runtime configuration. */
    public function embedManyUsing(array $chunks, array $configuration): array
    {
        if ($chunks === []) {
            return [];
        }

        $inputs = array_map(
            fn (string $chunk): string => $configuration['document_prefix'].$chunk,
            $chunks,
        );

        return Embeddings::for($inputs)
            ->dimensions($configuration['dimensions'])
            ->timeout(120)
            ->generate(Lab::Ollama, $configuration['model'])
            ->embeddings;
    }

    /** Embed a search query. */
    public function embedQuery(string $question, ?string $profile = null): array
    {
        return $this->embedQueryUsing($question, $this->profile($profile));
    }

    /** Embed a search query using a complete runtime configuration. */
    public function embedQueryUsing(string $question, array $configuration): array
    {
        return Embeddings::for([$configuration['query_prefix'].$question])
            ->dimensions($configuration['dimensions'])
            ->timeout(120)
            ->generate(Lab::Ollama, $configuration['model'])
            ->first();
    }

    /** Resolve an embedding profile from configuration. */
    private function profile(?string $profile): array
    {
        $profile ??= config('rag.defaults.embedding');
        $configuration = config("rag.embeddings.profiles.{$profile}");

        if ($configuration === null) {
            throw new InvalidArgumentException("Unknown embedding profile [{$profile}].");
        }

        return $configuration;
    }
}
