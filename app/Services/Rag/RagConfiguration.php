<?php

namespace App\Services\Rag;

use InvalidArgumentException;

class RagConfiguration
{
    /** Resolve the active chunking strategy and its options. */
    public function chunking(): array
    {
        $selected = config('rag.chunking');
        $strategy = config("rag-strategies.chunking.{$selected['strategy']}");

        if ($strategy === null) {
            throw new InvalidArgumentException("Unknown chunking strategy [{$selected['strategy']}].");
        }

        return [
            'name' => $selected['strategy'],
            'configuration' => [
                'strategy' => $strategy,
                'options' => $selected['options'],
            ],
        ];
    }

    /** Resolve the active embedding model and input mode. */
    public function embedding(): array
    {
        $selected = config('rag.embedding');
        $strategy = config("rag-strategies.embedding.{$selected['strategy']}");
        $mode = $strategy === null
            ? null
            : ($strategy['input_modes'][$selected['input_mode']] ?? null);

        if ($strategy === null || $mode === null) {
            throw new InvalidArgumentException(
                "Unknown embedding configuration [{$selected['strategy']}:{$selected['input_mode']}].",
            );
        }

        return [
            'name' => "{$selected['strategy']}:{$selected['input_mode']}",
            'configuration' => [
                'model' => $strategy['model'],
                'dimensions' => $strategy['dimensions'],
                ...$mode,
            ],
        ];
    }

    /** Resolve the active retrieval strategy and runtime options. */
    public function retrieval(): array
    {
        $selected = config('rag.retrieval');
        $strategy = config("rag-strategies.retrieval.{$selected['strategy']}");

        if ($strategy === null) {
            throw new InvalidArgumentException("Unknown retrieval strategy [{$selected['strategy']}].");
        }

        return [
            'name' => $selected['strategy'],
            'configuration' => [
                ...$strategy,
                ...$selected['options'],
            ],
        ];
    }
}
