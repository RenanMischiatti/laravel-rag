<?php

namespace App\Services\Rag;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DocumentImportService
{
    public function __construct(
        private readonly ChunkingService $chunkingService,
        private readonly EmbeddingService $embeddingService,
    ) {}

    /** Import a document and return its chunk count. */
    public function import(string $path): int
    {
        $filename = basename($path);
        $content = File::get($path);
        $chunks = $this->chunkingService->chunk($content);
        $embeddings = $this->embeddingService->embedMany($chunks);

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
