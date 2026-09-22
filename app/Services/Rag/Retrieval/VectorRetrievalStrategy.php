<?php

namespace App\Services\Rag\Retrieval;

use App\Contracts\Rag\RetrievalStrategy;
use App\Models\DocumentChunk;
use App\Services\Rag\EmbeddingService;
use Illuminate\Support\Collection;

class VectorRetrievalStrategy implements RetrievalStrategy
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /** Rank chunks by cosine distance between query and document embeddings. */
    public function retrieve(
        string $question,
        int $limit,
        ?float $minimumSimilarity,
        array $embeddingConfiguration,
        array $retrievalConfiguration,
    ): Collection {
        $embeddedQuestion = $this->embeddingService->embedQueryUsing($question, $embeddingConfiguration);
        $query = DocumentChunk::query()
            ->with('document:id,filename')
            ->whereNotNull('embedding')
            ->select('document_chunks.*')
            ->selectVectorDistance('embedding', $embeddedQuestion, 'distance');

        if ($minimumSimilarity !== null) {
            $query->whereVectorSimilarTo(
                'embedding',
                $embeddedQuestion,
                minSimilarity: $minimumSimilarity,
            );
        } else {
            $query->orderByVectorDistance('embedding', $embeddedQuestion);
        }

        return $query->limit($limit)->get();
    }
}
