<?php

use App\Factories\Rag\ChunkingStrategyFactory;
use App\Services\Rag\Chunking\FixedTextChunkingStrategy;
use App\Services\Rag\Chunking\ParagraphChunkingStrategy;
use App\Services\Rag\Chunking\SectionChunkingStrategy;
use App\Services\Rag\Chunking\SectionSemanticChunkingStrategy;
use App\Services\Rag\Chunking\SemanticChunkingStrategy;
use App\Services\Rag\EmbeddingService;
use Illuminate\Container\Container;

it('resolves strategies through the Laravel container', function () {
    $factory = new ChunkingStrategyFactory(new Container);

    expect($factory->make(SectionChunkingStrategy::class))
        ->toBeInstanceOf(SectionChunkingStrategy::class);
});

it('groups complete paragraphs up to the size limit', function () {
    $strategy = new ParagraphChunkingStrategy;

    $chunks = $strategy->chunk(
        "First paragraph.\n\nSecond paragraph.",
        ['max_characters' => 20],
    );

    expect($chunks)->toBe(['First paragraph.', 'Second paragraph.']);
});

it('creates fixed text chunks with overlap and no redundant tail', function () {
    $strategy = new FixedTextChunkingStrategy;

    $chunks = $strategy->chunk('abcdefghij', [
        'max_characters' => 6,
        'overlap' => 2,
    ]);

    expect($chunks)->toBe(['abcdef', 'efghij']);
});

it('keeps a section title in its chunks', function () {
    $strategy = new SectionChunkingStrategy(new ParagraphChunkingStrategy);
    $firstParagraph = str_repeat('Remote work ', 7);
    $secondParagraph = str_repeat('Vacation notice ', 7);
    $text = "==========\nPOLICIES\n==========\n\n{$firstParagraph}\n\n{$secondParagraph}";

    $chunks = $strategy->chunk($text, ['max_characters' => 110]);

    expect($chunks)
        ->toHaveCount(2)
        ->each->toStartWith('POLICIES');
});

it('groups adjacent paragraphs when their meanings are similar', function () {
    $embeddings = Mockery::mock(EmbeddingService::class);
    $embeddings->shouldReceive('embedManyUsing')->once()->andReturn([
        [1.0, 0.0],
        [0.9, 0.1],
        [0.0, 1.0],
    ]);

    $strategy = new SemanticChunkingStrategy($embeddings);
    $chunks = $strategy->chunk(
        "Remote work is available.\n\nHome office days require approval.\n\nTravel expenses need receipts.",
        [
            'max_characters' => 1000,
            'minimum_similarity' => 0.80,
            'embedding_configuration' => ['model' => 'test'],
        ],
    );

    expect($chunks)->toBe([
        "Remote work is available.\n\nHome office days require approval.",
        'Travel expenses need receipts.',
    ]);
});

it('keeps semantic chunks inside their original sections', function () {
    $embeddings = Mockery::mock(EmbeddingService::class);
    $embeddings->shouldReceive('embedManyUsing')->once()->andReturn([
        [1.0, 0.0],
        [0.9, 0.1],
        [1.0, 0.0],
        [0.9, 0.1],
    ]);

    $strategy = new SectionSemanticChunkingStrategy($embeddings);
    $text = "==========\nREMOTE WORK\n==========\n\nRemote work is available.\n\nApproval is required.\n\n==========\nEXPENSES\n==========\n\nReceipts are required.\n\nFinance approves expenses.";

    $chunks = $strategy->chunk($text, [
        'max_characters' => 1000,
        'minimum_similarity' => 0.80,
        'embedding_configuration' => ['model' => 'test'],
    ]);

    expect($chunks)->toBe([
        "REMOTE WORK\n\nRemote work is available.\n\nApproval is required.",
        "EXPENSES\n\nReceipts are required.\n\nFinance approves expenses.",
    ]);
});
