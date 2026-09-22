<?php

namespace App\Services\Rag\Retrieval;

use App\Contracts\Rag\RetrievalStrategy;
use App\Models\DocumentChunk;
use App\Services\Rag\EmbeddingService;
use Illuminate\Support\Collection;

class HybridRetrievalStrategy implements RetrievalStrategy
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /** Combine semantic and lexical rankings with reciprocal rank fusion. */
    public function retrieve(
        string $question,
        int $limit,
        ?float $minimumSimilarity,
        array $embeddingConfiguration,
        array $retrievalConfiguration,
    ): Collection {
        $embeddedQuestion = $this->embeddingService->embedQueryUsing($question, $embeddingConfiguration);
        $candidateLimit = max($limit, $retrievalConfiguration['candidate_limit']);
        $vectorChunks = $this->vectorSearch($embeddedQuestion, $candidateLimit);
        $lexicalChunks = $this->lexicalSearch($question, $embeddedQuestion, $candidateLimit);

        return $this->fuse(
            $vectorChunks,
            $lexicalChunks,
            $retrievalConfiguration['rrf_k'],
        )
            ->when(
                $minimumSimilarity !== null,
                fn (Collection $chunks): Collection => $chunks->filter(
                    fn (DocumentChunk $chunk): bool => 1 - (float) $chunk->distance >= $minimumSimilarity,
                ),
            )
            ->take($limit)
            ->values();
    }

    /** Retrieve chunks by cosine similarity. */
    private function vectorSearch(array $embedding, int $limit): Collection
    {
        return DocumentChunk::query()
            ->with('document:id,filename')
            ->whereNotNull('embedding')
            ->select('document_chunks.*')
            ->selectVectorDistance('embedding', $embedding, 'distance')
            ->orderByVectorDistance('embedding', $embedding)
            ->limit($limit)
            ->get();
    }

    /** Retrieve chunks containing words from the question. */
    private function lexicalSearch(string $question, array $embedding, int $limit): Collection
    {
        return DocumentChunk::query()
            ->with('document:id,filename')
            ->whereRaw(
                "to_tsvector('portuguese', content) @@ to_tsquery('portuguese', array_to_string(tsvector_to_array(to_tsvector('portuguese', ?)), ' | '))",
                [$question],
            )
            ->select('document_chunks.*')
            ->selectVectorDistance('embedding', $embedding, 'distance')
            ->selectRaw(
                "ts_rank_cd(to_tsvector('portuguese', content), to_tsquery('portuguese', array_to_string(tsvector_to_array(to_tsvector('portuguese', ?)), ' | '))) AS lexical_score",
                [$question],
            )
            ->orderByDesc('lexical_score')
            ->limit($limit)
            ->get();
    }

    /** Reward chunks that rank well in either or both searches. */
    private function fuse(Collection $vectorChunks, Collection $lexicalChunks, int $rrfK): Collection
    {
        $chunks = $vectorChunks->concat($lexicalChunks)->keyBy('id');
        $scores = [];
        $vectorRanks = [];
        $lexicalRanks = [];

        foreach ($vectorChunks as $position => $chunk) {
            $scores[$chunk->id] = ($scores[$chunk->id] ?? 0) + 1 / ($rrfK + $position + 1);
            $vectorRanks[$chunk->id] = $position + 1;
        }

        foreach ($lexicalChunks as $position => $chunk) {
            $scores[$chunk->id] = ($scores[$chunk->id] ?? 0) + 1 / ($rrfK + $position + 1);
            $lexicalRanks[$chunk->id] = $position + 1;
        }

        return $chunks
            ->map(function (DocumentChunk $chunk) use ($scores, $vectorRanks, $lexicalRanks): DocumentChunk {
                $chunk->setAttribute('rrf_score', $scores[$chunk->id]);
                $chunk->setAttribute('vector_rank', $vectorRanks[$chunk->id] ?? null);
                $chunk->setAttribute('lexical_rank', $lexicalRanks[$chunk->id] ?? null);

                return $chunk;
            })
            ->sortByDesc('rrf_score')
            ->values();
    }
}
