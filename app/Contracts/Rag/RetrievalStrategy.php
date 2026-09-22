<?php

namespace App\Contracts\Rag;

use Illuminate\Support\Collection;

interface RetrievalStrategy
{
    /** Retrieve ranked chunks for a question. */
    public function retrieve(
        string $question,
        int $limit,
        ?float $minimumSimilarity,
        array $embeddingConfiguration,
        array $retrievalConfiguration,
    ): Collection;
}
