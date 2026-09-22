<?php

use App\Factories\Rag\RetrievalStrategyFactory;
use App\Services\Rag\Retrieval\VectorRetrievalStrategy;
use Illuminate\Container\Container;

it('resolves retrieval strategies through the Laravel container', function () {
    $factory = new RetrievalStrategyFactory(new Container);

    expect($factory->make(VectorRetrievalStrategy::class))
        ->toBeInstanceOf(VectorRetrievalStrategy::class);
});
