<?php

namespace App\Console\Commands;

use App\Services\Rag\DocumentIndexService;
use App\Services\Rag\RagConfiguration;
use App\Services\RagEvaluation\EvaluationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EvaluateRag extends Command
{
    protected $signature = 'rag:evaluate
                            {--retrieval-only : Evaluate retrieval without asking the language model}';

    protected $description = 'Evaluate RAG retrieval and answer quality';

    /** Run the evaluation dataset and display its metrics. */
    public function handle(
        EvaluationRunner $evaluator,
        DocumentIndexService $indexer,
        RagConfiguration $configuration,
    ): int {
        $index = $indexer->rebuildCurrent();
        $this->printConfiguration($configuration, $index['chunks']);

        $cases = config('rag-evaluation.cases', []);

        $evaluation = $evaluator->evaluate(
            $cases,
            generateAnswers: ! $this->option('retrieval-only'),
        );

        $this->printResults($evaluation);

        return self::SUCCESS;
    }

    /** Display the complete configuration used by this evaluation. */
    private function printConfiguration(RagConfiguration $configuration, int $chunks): void
    {
        $chunking = $configuration->chunking();
        $embedding = $configuration->embedding();
        $retrieval = $configuration->retrieval();

        $this->info('Evaluation configuration');
        $this->line('Chunking: '.$chunking['name']);
        $this->line('Embedding: '.$embedding['name']);
        $this->line('Retrieval: '.$retrieval['name']);
        $this->line('Minimum similarity: '.$retrieval['configuration']['minimum_similarity']);
        $this->line('Context limit: '.$retrieval['configuration']['context_limit']);
        $this->line('Index chunks: '.$chunks);
        $this->newLine();
    }

    private function printResults(array $evaluation): void
    {
        $this->table(
            ['Question', 'Retrieval', 'Rank', 'Judge', 'Score', 'Reason'],
            collect($evaluation['results'])
                ->map(fn (array $result): array => [
                    Str::limit($result['question'], 45),
                    $result['retrieval_passed'] ? 'PASS' : 'FAIL',
                    $result['rank'] ?? '-',
                    $result['judgement'] === null
                        ? 'SKIPPED'
                        : ($result['judgement']['passed'] ? 'PASS' : 'FAIL'),
                    $result['judgement']['overall_score'] ?? '-',
                    Str::limit(Str::squish($result['judgement']['reason'] ?? '-'), 60),
                ])
                ->all(),
        );

        $summary = $evaluation['summary'];

        $this->newLine();
        $this->info('Evaluation summary');
        $this->line('Questions: '.$summary['questions']);
        $this->line('Context Hit@K: '.$this->percentage($summary['hit_at_k']));
        $this->line('MRR: '.number_format($summary['mrr'], 3));
        $this->line('Judge pass rate: '.($summary['judge_pass_rate'] === null
            ? 'skipped'
            : $this->percentage($summary['judge_pass_rate'])));

        if ($summary['judge_scores'] !== null) {
            $this->line('Correctness: '.$summary['judge_scores']['correctness'].'/5');
            $this->line('Faithfulness: '.$summary['judge_scores']['faithfulness'].'/5');
            $this->line('Relevance: '.$summary['judge_scores']['relevance'].'/5');
            $this->line('Completeness: '.$summary['judge_scores']['completeness'].'/5');
            $this->line('Overall judge score: '.$summary['judge_scores']['overall_score'].'/5');
        }
    }

    /** Format a decimal metric as a percentage. */
    private function percentage(float $value): string
    {
        return number_format($value * 100, 1).'%';
    }
}
