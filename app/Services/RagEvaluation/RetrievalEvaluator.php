<?php

namespace App\Services\RagEvaluation;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RetrievalEvaluator
{
    /** Find the expected chunk and calculate its reciprocal rank. */
    public function evaluate(Collection $chunks, array $case): array
    {
        $index = $chunks->search(function (DocumentChunk $chunk) use ($case): bool {
            $correctDocument = $chunk->document->filename === $case['expected_document'];
            $correctContent = $this->containsAll(
                $chunk->content,
                $case['expected_context_contains'],
            );

            return $correctDocument && $correctContent;
        });

        $rank = $index === false ? null : $index + 1;

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
