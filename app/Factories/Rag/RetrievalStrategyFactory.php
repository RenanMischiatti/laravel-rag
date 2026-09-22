<?php

namespace App\Factories\Rag;

use App\Contracts\Rag\RetrievalStrategy;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class RetrievalStrategyFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /** Resolve a retrieval strategy through Laravel's service container. */
    public function make(string $strategyClass): RetrievalStrategy
    {
        $strategy = $this->container->make($strategyClass);

        if (! $strategy instanceof RetrievalStrategy) {
            throw new InvalidArgumentException(
                "Retrieval strategy [{$strategyClass}] must implement RetrievalStrategy.",
            );
        }

        return $strategy;
    }
}
