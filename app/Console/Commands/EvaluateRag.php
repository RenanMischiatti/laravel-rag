<?php

namespace App\Console\Commands;

use App\Services\RagEvaluation\EvaluationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EvaluateRag extends Command
{
    protected $signature = 'rag:evaluate
                            {--retrieval-only : Evaluate retrieval without asking the language model}';

    protected $description = 'Evaluate RAG retrieval and answer quality';

    /** Run the evaluation dataset and display its metrics. */
    public function handle(EvaluationRunner $evaluator): int
    {
        $cases = config('rag-evaluation.cases', []);

        if ($cases === []) {
            $this->error('No evaluation cases were configured.');
            return self::FAILURE;
        }

        $evaluation = $evaluator->evaluate(
            $cases,
            generateAnswers: ! $this->option('retrieval-only'),
        );

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
        $this->line('Hit@K: '.$this->percentage($summary['hit_at_k']));
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

        return self::SUCCESS;
    }

    /** Format a decimal metric as a percentage. */
    private function percentage(float $value): string
    {
        return number_format($value * 100, 1).'%';
    }
}
