<?php

namespace App\Console\Commands;

use App\Services\RagEvaluation\Experiments\ExperimentRunner;
use Illuminate\Console\Command;

class ExperimentRag extends Command
{
    protected $signature = 'rag:experiment
                            {--top=10 : Number of configurations to display}
                            {--all : Display every evaluated configuration}';

    protected $description = 'Compare retrieval settings using the current RAG index';

    /** Run a grid search over the current retrieval settings. */
    public function handle(ExperimentRunner $runner): int
    {
        $this->printHeader();

        $cases = config('rag-evaluation.cases', []);
        $configuration = config('rag-evaluation.experiments');

        $this->line('Quality target: '.$this->percentage($configuration['minimum_hit_rate']));
        $this->line('Qualified configurations are ranked by the smallest average context.');
        $this->newLine();

        $results = $runner->run(
            $cases,
            $configuration,
            progress: fn (string $message) => $this->line($message.'...'),
        );

        $this->printResults($results);

        return self::SUCCESS;
    }

    private function printHeader(): void
    {
        $this->info('RAG Retrieval Experiment');
        $this->line('This experiment evaluates retrieval quality for the current RAG index.');
        $this->newLine();
    }

    private function printResults(array $results): void
    {
        $displayedResults = $this->option('all')
            ? collect($results)
            : collect($results)->take(max(1, (int) $this->option('top')));

        $this->table(
            ['#', 'Chunking', 'Embedding', 'Retrieval', 'Similarity', 'Limit', 'Hit@K', 'MRR', 'Avg context', 'Index chunks'],
            $displayedResults->map(fn (array $result, int $index): array => [
                $index + 1,
                $result['chunking_profile'],
                $result['embedding_profile'],
                $result['retrieval_profile'],
                $result['minimum_similarity'] ?? 'none',
                $result['context_limit'],
                $this->percentage($result['hit_at_k']),
                number_format($result['mrr'], 3),
                number_format($result['average_chunks'], 2),
                $result['index_chunks'],
            ])->all(),
        );

        $best = $results[0];

        $this->newLine();
        $this->info('Best cost-benefit configuration');
        $this->line('Chunking: '.$best['chunking_profile']);
        $this->line('Embedding: '.$best['embedding_profile']);
        $this->line('Retrieval: '.$best['retrieval_profile']);
        $this->line('Minimum similarity: '.($best['minimum_similarity'] ?? 'none'));
        $this->line('Context limit: '.$best['context_limit']);
        $this->line('Hit@K: '.$this->percentage($best['hit_at_k']));
        $this->line('MRR: '.number_format($best['mrr'], 3));
        $this->line('Average context: '.number_format($best['average_chunks'], 2).' chunks');
        $this->newLine();
        $this->comment('The winner meets the quality target with the smallest average context. Validate finalists end to end before changing production defaults.');
    }

    /** Format a decimal metric as a percentage. */
    private function percentage(float $value): string
    {
        return number_format($value * 100, 1).'%';
    }
}
