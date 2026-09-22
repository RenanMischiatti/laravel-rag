<?php

namespace App\Services\Rag;

use App\Factories\Rag\RetrievalStrategyFactory;
use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DocumentRetriever
{
    public function __construct(
        private readonly RetrievalStrategyFactory $retrievalFactory,
    ) {}

    /** Find the chunks that are closest to the question. */
    public function retrieve(string $question): Collection
    {
        $profile = config('rag.defaults.retrieval');
        $configuration = $this->configuration($profile);

        Log::info('RAG retrieval started.', [
            'question' => $question,
            'retrieval_profile' => $profile,
            'context_limit' => $configuration['context_limit'],
            'minimum_similarity' => $configuration['minimum_similarity'],
        ]);

        return $this->search(
            $question,
            $profile,
            $configuration['context_limit'],
            $configuration['minimum_similarity'],
            config('rag.embeddings.profiles.'.config('rag.defaults.embedding')),
        );
    }

    /** Retrieve a larger unfiltered candidate set for experiments. */
    public function retrieveCandidates(
        string $question,
        int $limit,
        string $retrievalProfile,
        string $embeddingName,
        array $embeddingConfiguration,
    ): Collection {
        Log::info('RAG candidate retrieval started.', [
            'question' => $question,
            'candidate_limit' => $limit,
            'retrieval_profile' => $retrievalProfile,
            'embedding_profile' => $embeddingName,
        ]);

        return $this->search(
            $question,
            $retrievalProfile,
            $limit,
            null,
            $embeddingConfiguration,
        );
    }

    /** Execute one registered retrieval strategy. */
    private function search(
        string $question,
        string $profile,
        int $limit,
        ?float $minimumSimilarity,
        array $embeddingConfiguration,
    ): Collection {
        $configuration = $this->configuration($profile);

        $strategy = $this->retrievalFactory->make($configuration['strategy']);
        $chunks = $strategy->retrieve(
            $question,
            $limit,
            $minimumSimilarity,
            $embeddingConfiguration,
        );

        Log::info('RAG chunks retrieved.', [
            'count' => $chunks->count(),
            'chunks' => $chunks
                ->map(fn (DocumentChunk $chunk, int $index): array => [
                    'rank' => $index + 1,
                    'document' => $chunk->document->filename,
                    'position' => $chunk->position,
                    'distance' => round((float) $chunk->distance, 4),
                    'similarity' => round(1 - (float) $chunk->distance, 4),
                    'content' => $chunk->content,
                ])
                ->all(),
        ]);

        return $chunks;
    }

    /** Resolve a retrieval profile from configuration. */
    private function configuration(string $profile): array
    {
        $configuration = config("rag.retrieval.profiles.{$profile}");

        if ($configuration === null) {
            throw new InvalidArgumentException("Unknown retrieval profile [{$profile}].");
        }

        return $configuration;
    }
}
