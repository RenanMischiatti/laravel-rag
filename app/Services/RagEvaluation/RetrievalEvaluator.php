<?php

namespace App\Services\RagEvaluation;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RetrievalEvaluator
{
    /** Find the rank where the retrieved context contains all expected evidence. */
    public function evaluate(Collection $chunks, array $case): array
    {
        $evidence = '';
        $rank = null;

        foreach ($chunks as $index => $chunk) {
            if ($chunk->document->filename === $case['expected_document']) {
                $evidence .= "\n".$chunk->content;
            }

            if ($this->containsAll($evidence, $case['expected_context_contains'])) {
                $rank = $index + 1;

                break;
            }
        }

        return [
            'passed' => $rank !== null,
            'rank' => $rank,
            'reciprocal_rank' => $rank === null ? 0 : 1 / $rank,
        ];
    }

    /** Check required facts without depending on accents or letter case. */
    private function containsAll(string $text, array $expectedValues): bool
    {
        $normalizedText = $this->normalize($text);

        return collect($expectedValues)
            ->every(fn (string $value): bool => str_contains(
                $normalizedText,
                $this->normalize($value),
            ));
    }

    /** Normalize text before an exact comparison. */
    private function normalize(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }
}
