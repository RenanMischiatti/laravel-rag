<?php

use App\Models\DocumentChunk;
use App\Services\Rag\EmbeddingService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;

uses(TestCase::class);

it('creates embeddings for multiple chunks in one generation', function () {
    Embeddings::fake();

    $embeddings = (new EmbeddingService)->embedMany([
        'First chunk',
        'Second chunk',
    ]);

    expect($embeddings)->toHaveCount(2)
        ->and($embeddings[0])->toHaveCount(768)
        ->and($embeddings[1])->toHaveCount(768);

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->inputs === [
        'search_document: First chunk',
        'search_document: Second chunk',
    ] && $prompt->dimensions === 768
        && $prompt->model === 'nomic-embed-text-v2-moe');
});

it('formats an embedding for the pgvector column', function () {
    $chunk = new DocumentChunk([
        'embedding' => [0.25, -0.5, 0.75],
    ]);

    expect($chunk->getAttributes()['embedding'])->toBe('[0.25,-0.5,0.75]');
});
