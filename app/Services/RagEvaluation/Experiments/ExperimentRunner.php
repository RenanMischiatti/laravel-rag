<?php

namespace App\Services\RagEvaluation\Experiments;

use App\Models\DocumentChunk;
use App\Services\Rag\DocumentIndexService;
use App\Services\Rag\DocumentRetriever;
use App\Services\RagEvaluation\RetrievalEvaluator;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExperimentRunner
{
    public function __construct(
        private readonly DocumentIndexService $indexer,
        private readonly DocumentRetriever $retriever,
        private readonly RetrievalEvaluator $retrievalEvaluator,
        private readonly ConfigurationGenerator $generator,
    ) {}

    /** Count every index and retrieval configuration before execution. */
    public function count(array $configuration): array
    {
        $indexes = $this->indexConfigurations($configuration)->count();
        $retrievals = count($configuration['retrieval_profiles'])
            * count($configuration['minimum_similarities'])
            * count($configuration['context_limits']);

        return [
            'indexes' => $indexes,
            'configurations' => $indexes * $retrievals,
        ];
    }

    /** Rebuild and evaluate every configured RAG combination. */
    public function run(array $cases, array $configuration, ?Closure $progress = null): array
    {
        $results = [];

        try {
            foreach ($this->indexConfigurations($configuration) as [$chunking, $embedding]) {
                $progress?->__invoke("Indexing {$chunking['name']} + {$embedding['name']}");
                $index = $this->indexer->rebuildUsing(
                    $chunking['name'],
                    $chunking['configuration'],
                    $embedding['name'],
                    $embedding['configuration'],
                );

                foreach ($configuration['retrieval_profiles'] as $retrieval) {
                    $datasets = $this->retrieveDatasets(
                        $cases,
                        $configuration['candidate_limit'],
                        $retrieval,
                        $embedding,
                    );

                    foreach ($this->retrievalConfigurations($configuration) as [$threshold, $limit]) {
                        $results[] = $this->evaluateConfiguration(
                            $datasets,
                            $chunking['name'],
                            $embedding['name'],
                            $retrieval,
                            $index['chunks'],
                            $threshold,
                            $limit,
                        );
                    }
                }
            }
        } finally {
            $progress?->__invoke('Restoring the default RAG index');
            $this->indexer->rebuild(
                config('rag.defaults.chunking'),
                config('rag.defaults.embedding'),
            );
        }

        $results = collect($results)->map(fn (array $result): array => [
            ...$result,
            'meets_quality_target' => $result['hit_at_k'] >= $configuration['minimum_hit_rate'],
        ]);

        $hasQualifiedResults = $results->contains('meets_quality_target', true);
        $sorting = $hasQualifiedResults
            ? [
                ['meets_quality_target', 'desc'],
                ['average_chunks', 'asc'],
                ['hit_at_k', 'desc'],
                ['mrr', 'desc'],
                ['index_chunks', 'asc'],
            ]
            : [
                ['hit_at_k', 'desc'],
                ['mrr', 'desc'],
                ['average_chunks', 'asc'],
                ['index_chunks', 'asc'],
            ];

        $results = $results
            ->sortBy($sorting)
            ->values()
            ->all();

        Log::info('RAG experiment completed.', [
            'configurations' => count($results),
            'minimum_hit_rate' => $configuration['minimum_hit_rate'],
            'best_configuration' => $results[0] ?? null,
        ]);

        return $results;
    }

    /** Build every configured chunking and embedding profile pair. */
    private function indexConfigurations(array $configuration): Collection
    {
        return $this->generator
            ->chunking($configuration['chunking_strategies'])
            ->crossJoin($this->generator->embeddings($configuration['embedding_strategies']));
    }

    /** Retrieve candidates once so thresholds and limits are simulated in memory. */
    private function retrieveDatasets(
        array $cases,
        int $candidateLimit,
        string $retrievalProfile,
        array $embedding,
    ): Collection {
        return collect($cases)->map(fn (array $case): array => [
            'case' => $case,
            'candidates' => $this->retriever->retrieveCandidates(
                $case['question'],
                $candidateLimit,
                $retrievalProfile,
                $embedding['name'],
                $embedding['configuration'],
            ),
        ]);
    }

    /** Build every configured threshold and context limit pair. */
    private function retrievalConfigurations(array $configuration): Collection
    {
        return collect($configuration['minimum_similarities'])
            ->crossJoin($configuration['context_limits']);
    }

    /** Calculate retrieval metrics for one complete RAG configuration. */
    private function evaluateConfiguration(
        Collection $datasets,
        string $chunkingProfile,
        string $embeddingProfile,
        string $retrievalProfile,
        int $indexChunks,
        ?float $threshold,
        int $contextLimit,
    ): array {
        $evaluations = $datasets->map(function (array $dataset) use ($threshold, $contextLimit): array {
            $chunks = $this->applyRetrievalSettings(
                $dataset['candidates'],
                $threshold,
                $contextLimit,
            );

            return [
                ...$this->retrievalEvaluator->evaluate($chunks, $dataset['case']),
                'chunks' => $chunks->count(),
            ];
        });

        return [
            'chunking_profile' => $chunkingProfile,
            'embedding_profile' => $embeddingProfile,
            'retrieval_profile' => $retrievalProfile,
            'minimum_similarity' => $threshold,
            'context_limit' => $contextLimit,
            'hit_at_k' => (float) $evaluations->avg('passed'),
            'mrr' => (float) $evaluations->avg('reciprocal_rank'),
            'average_chunks' => round((float) $evaluations->avg('chunks'), 2),
            'index_chunks' => $indexChunks,
        ];
    }

    /** Apply cheap retrieval parameters to an already ranked candidate set. */
    private function applyRetrievalSettings(
        Collection $candidates,
        ?float $threshold,
        int $contextLimit,
    ): Collection {
        return $candidates
            ->when(
                $threshold !== null,
                fn (Collection $chunks): Collection => $chunks->filter(
                    fn (DocumentChunk $chunk): bool => 1 - (float) $chunk->distance >= $threshold,
                ),
            )
            ->take($contextLimit)
            ->values();
    }
}
