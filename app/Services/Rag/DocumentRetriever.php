<?php

namespace App\Services\Rag;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DocumentRetriever
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /** Find the chunks that are closest to the question. */
    public function retrieve(string $question): Collection
    {
        Log::info('RAG retrieval started.', [
            'question' => $question,
            'context_limit' => config('rag.context_limit'),
            'minimum_similarity' => config('rag.minimum_similarity'),
        ]);

        $embeddedQuestion = $this->embeddingService->embedQuery($question);

        Log::info('RAG question embedded.', [
            'dimensions' => count($embeddedQuestion),
        ]);

        $chunks = DocumentChunk::query()
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
}
