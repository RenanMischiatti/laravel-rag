<?php

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\RagEvaluation\RetrievalEvaluator;

it('finds evidence accumulated across multiple retrieved chunks', function () {
    $document = new Document(['filename' => 'policies.txt']);
    $chunks = collect([
        new DocumentChunk(['content' => 'A processing failure caused duplicate charges.']),
        new DocumentChunk(['content' => 'The financial impact was R$ 150.000,00.']),
    ])->each->setRelation('document', $document);

    $result = (new RetrievalEvaluator)->evaluate($chunks, [
        'expected_document' => 'policies.txt',
        'expected_context_contains' => ['duplicate charges', 'R$ 150.000,00'],
    ]);

    expect($result)->toBe([
        'passed' => true,
        'rank' => 2,
        'reciprocal_rank' => 0.5,
    ]);
});

it('ignores evidence retrieved from another document', function () {
    $chunk = new DocumentChunk(['content' => 'The financial impact was R$ 150.000,00.']);
    $chunk->setRelation('document', new Document(['filename' => 'other.txt']));

    $result = (new RetrievalEvaluator)->evaluate(collect([$chunk]), [
        'expected_document' => 'policies.txt',
        'expected_context_contains' => ['R$ 150.000,00'],
    ]);

    expect($result['passed'])->toBeFalse()
        ->and($result['rank'])->toBeNull()
        ->and($result['reciprocal_rank'])->toBe(0);
});
