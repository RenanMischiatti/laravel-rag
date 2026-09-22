<?php

namespace App\Services\Rag;

use App\Factories\Rag\ChunkingStrategyFactory;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DocumentImportService
{
    public function __construct(
        private readonly ChunkingStrategyFactory $chunkingFactory,
        private readonly EmbeddingService $embeddingService,
    ) {}

    /** Import a document and return its chunk count. */
    public function import(
        string $path,
        ?string $chunkingProfile = null,
        ?string $embeddingProfile = null,
    ): int {
        return $this->importUsing(
            $path,
            config('rag.chunking.profiles.'.($chunkingProfile ?? config('rag.defaults.chunking'))),
            config('rag.embeddings.profiles.'.($embeddingProfile ?? config('rag.defaults.embedding'))),
        );
    }

    /** Import a document using complete runtime configurations. */
    public function importUsing(
        string $path,
        array $chunkingConfiguration,
        array $embeddingConfiguration,
    ): int {
        $filename = basename($path);
        $content = File::get($path);
        $chunking = $this->chunkingFactory->make($chunkingConfiguration['strategy']);
        $chunks = $chunking->chunk($content, [
            ...$chunkingConfiguration['options'],
            'embedding_configuration' => $embeddingConfiguration,
        ]);
        $embeddings = $this->embeddingService->embedManyUsing($chunks, $embeddingConfiguration);

        $chunks = collect($chunks)
            ->map(fn (string $chunk, int $position): array => [
                'position' => $position,
                'content' => $chunk,
                'embedding' => $embeddings[$position],
            ])
            ->all();

        DB::transaction(function () use ($filename, $content, $chunks): void {
            $document = Document::updateOrCreate(
                ['filename' => $filename],
                ['content' => $content],
            );

            $document->chunks()->delete();
            $document->chunks()->createMany($chunks);
        });

        return count($chunks);
    }
}
