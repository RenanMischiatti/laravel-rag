<?php

namespace App\Factories\Rag;

use App\Contracts\Rag\ChunkingStrategy;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ChunkingStrategyFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /** Resolve a chunking strategy through Laravel's service container. */
    public function make(string $strategyClass): ChunkingStrategy
    {
        $strategy = $this->container->make($strategyClass);

        if (! $strategy instanceof ChunkingStrategy) {
            throw new InvalidArgumentException(
                "Chunking strategy [{$strategyClass}] must implement ChunkingStrategy.",
            );
        }

        return $strategy;
    }
}
