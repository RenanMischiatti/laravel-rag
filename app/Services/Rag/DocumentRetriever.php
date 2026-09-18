<?php

namespace App\Services\Rag;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;

class DocumentRetriever
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /** Find the chunks that are closest to the question. */
    public function retrieve(string $question): Collection
    {
        $embeddedQuestion = $this->embeddingService->embedQuery($question);

        return DocumentChunk::query()
            ->with('document:id,filename')
            ->whereNotNull('embedding')
            ->select('document_chunks.*')
            ->selectVectorDistance('embedding', $embeddedQuestion, 'distance')
            ->whereVectorSimilarTo(
                'embedding',
                $embeddedQuestion,
                minSimilarity: config('rag.minimum_similarity'),
            )
            ->limit(config('rag.context_limit'))
            ->get();
    }
}
