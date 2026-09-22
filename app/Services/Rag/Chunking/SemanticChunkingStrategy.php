<?php

namespace App\Services\Rag\Chunking;

use App\Contracts\Rag\ChunkingStrategy;
use App\Services\Rag\EmbeddingService;

class SemanticChunkingStrategy implements ChunkingStrategy
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    /** Group adjacent text units while they discuss related content. */
    public function chunk(string $text, array $options): array
    {
        $units = collect($this->splitUnits($text, $options['max_characters']))
            ->map(fn (string $content): array => [
                'content' => $content,
                'embedding_content' => $content,
                'group' => 'document',
                'prefix' => '',
            ])
            ->all();

        return $this->groupUnits($units, $options);
    }

    /** Split paragraphs and expand oversized tables or lists by line. */
    protected function splitUnits(string $text, int $maxCharacters): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $blocks = array_filter(array_map(
            'trim',
            preg_split('/\n\s*\n/', $text) ?: [],
        ));

        return collect($blocks)
            ->flatMap(function (string $block) use ($maxCharacters): array {
                if (mb_strlen($block) <= $maxCharacters) {
                    return [$block];
                }

                return collect(explode("\n", $block))
                    ->flatMap(fn (string $line): array => mb_str_split(trim($line), $maxCharacters))
                    ->filter()
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    /** Join related units without crossing their structural group. */
    protected function groupUnits(array $units, array $options): array
    {
        if ($units === []) {
            return [];
        }

        $vectors = $this->embeddings->embedManyUsing(
            array_column($units, 'embedding_content'),
            $options['embedding_configuration'],
        );
        $chunks = [];
        $current = [$units[0]];

        for ($position = 1; $position < count($units); $position++) {
            $unit = $units[$position];
            $sameGroup = $unit['group'] === $units[$position - 1]['group'];
            $related = $this->cosineSimilarity(
                $vectors[$position - 1],
                $vectors[$position],
            ) >= $options['minimum_similarity'];
            $candidate = $this->formatChunk([...$current, $unit]);

            if (! $sameGroup || ! $related || mb_strlen($candidate) > $options['max_characters']) {
                $chunks[] = $this->formatChunk($current);
                $current = [$unit];

                continue;
            }

            $current[] = $unit;
        }

        $chunks[] = $this->formatChunk($current);

        return $chunks;
    }

    /** Add the section title once above the grouped content. */
    private function formatChunk(array $units): string
    {
        $prefix = $units[0]['prefix'];
        $content = collect($units)->pluck('content')->implode("\n\n");

        return $prefix === '' ? $content : $prefix."\n\n".$content;
    }

    /** Measure the semantic similarity between two vectors. */
    private function cosineSimilarity(array $first, array $second): float
    {
        $dotProduct = 0.0;
        $firstLength = 0.0;
        $secondLength = 0.0;

        foreach ($first as $position => $value) {
            $dotProduct += $value * $second[$position];
            $firstLength += $value ** 2;
            $secondLength += $second[$position] ** 2;
        }

        if ($firstLength === 0.0 || $secondLength === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($firstLength) * sqrt($secondLength));
    }
}
