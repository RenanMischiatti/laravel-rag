<?php

namespace App\Services\RagEvaluation;

use App\Services\Rag\DocumentRetriever;
use App\Services\Rag\RagService;
use Illuminate\Support\Facades\Log;

class EvaluationRunner
{
    public function __construct(
        private readonly DocumentRetriever $retriever,
        private readonly RagService $rag,
        private readonly RetrievalEvaluator $retrievalEvaluator,
        private readonly AnswerJudge $answerJudge,
        private readonly MetricsCalculator $metrics,
    ) {}

    /** Run the evaluation dataset and return every result with its summary. */
    public function evaluate(array $cases, bool $generateAnswers = true): array
    {
        $results = collect($cases)
            ->map(fn (array $case): array => $this->evaluateCase($case, $generateAnswers))
            ->all();

        $summary = $this->metrics->summarize($results, $generateAnswers);

        Log::info('RAG evaluation completed.', $summary);

        return compact('results', 'summary');
    }

    /** Evaluate retrieval and generation for one question. */
    private function evaluateCase(array $case, bool $generateAnswer): array
    {
        $chunks = $this->retriever->retrieve($case['question']);
        $retrieval = $this->retrievalEvaluator->evaluate($chunks, $case);
        
        $answer = $generateAnswer
            ? $this->rag->answerFromChunks($case['question'], $chunks)
            : null;

        $judgement = $generateAnswer
            ? $this->answerJudge->judge($case, $chunks, $answer)
            : null;

        $result = [
            'question' => $case['question'],
            'retrieval_passed' => $retrieval['passed'],
            'rank' => $retrieval['rank'],
            'reciprocal_rank' => $retrieval['reciprocal_rank'],
            'answer' => $answer,
            'judgement' => $judgement,
        ];

        Log::info('RAG evaluation case completed.', $result);

        return $result;
    }
}
