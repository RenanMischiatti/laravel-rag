<?php

namespace App\Services\RagEvaluation;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;

class AnswerJudge
{
    /** Ask an LLM to score the generated answer against the evidence. */
    public function judge(array $case, Collection $chunks, string $answer): array
    {
        $response = RagEvaluationAgent::make()->prompt(
            $this->buildPrompt($case, $chunks, $answer),
            provider: Lab::Ollama,
            model: config('rag-evaluation.judge.model'),
            timeout: config('rag.timeout'),
        );

        $scores = $response->toArray();
        $overallScore = collect($this->scoreNames())
            ->avg(fn (string $name): int => $scores[$name]);

        return [
            ...$scores,
            'overall_score' => round($overallScore, 2),
            'passed' => $overallScore >= config('rag-evaluation.judge.pass_score'),
        ];
    }

    /** Build the evidence package evaluated by the judge. */
    private function buildPrompt(array $case, Collection $chunks, string $answer): string
    {
        $context = $chunks
            ->map(fn (DocumentChunk $chunk): string => <<<TEXT
                [{$chunk->document->filename} #{$chunk->position}]
                {$chunk->content}
            TEXT)
            ->implode("\n\n");

        return <<<PROMPT
            Question:
            {$case['question']}

            Reference answer:
            {$case['reference_answer']}

            Retrieved context:
            {$context}

            Generated answer:
            {$answer}
        PROMPT;
    }

    /** List the fields included in the final average. */
    private function scoreNames(): array
    {
        return ['correctness', 'faithfulness', 'relevance', 'completeness'];
    }
}
