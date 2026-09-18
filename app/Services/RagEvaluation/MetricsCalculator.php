<?php

namespace App\Services\RagEvaluation;

class MetricsCalculator
{
    /** Calculate mathematical retrieval and LLM judge averages. */
    public function summarize(array $results, bool $answersGenerated): array
    {
        $results = collect($results);
        $total = $results->count();

        return [
            'questions' => $total,
            'hit_at_k' => $this->average($results->pluck('retrieval_passed')),
            'mrr' => $this->average($results->pluck('reciprocal_rank')),
            'judge_pass_rate' => $answersGenerated
                ? $this->average($results->pluck('judgement.passed'))
                : null,
            'judge_scores' => $answersGenerated
                ? $this->averageJudgeScores($results)
                : null,
        ];
    }

    /** Calculate the average score for each judge criterion. */
    private function averageJudgeScores($results): array
    {
        return collect(['correctness', 'faithfulness', 'relevance', 'completeness', 'overall_score'])
            ->mapWithKeys(fn (string $name): array => [
                $name => round($this->average($results->pluck("judgement.{$name}")), 2),
            ])
            ->all();
    }

    /** Return zero when a dataset has no values. */
    private function average($values): float
    {
        return $values->isEmpty() ? 0 : (float) $values->avg();
    }
}
