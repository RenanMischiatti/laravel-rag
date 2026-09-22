<?php

namespace App\Services\Rag;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DocumentIndexService
{
    public function __construct(
        private readonly DocumentImportService $importer,
    ) {}

    /** Rebuild every document chunk using one indexing configuration. */
    public function rebuild(string $chunkingProfile, string $embeddingProfile): array
    {
        return $this->rebuildUsing(
            $chunkingProfile,
            config("rag.chunking.profiles.{$chunkingProfile}"),
            $embeddingProfile,
            config("rag.embeddings.profiles.{$embeddingProfile}"),
        );
    }

    /** Rebuild the index using generated experiment configurations. */
    public function rebuildUsing(
        string $chunkingName,
        array $chunkingConfiguration,
        string $embeddingName,
        array $embeddingConfiguration,
    ): array {
        $files = File::glob(base_path('documents/*.txt'));

        DocumentChunk::query()->delete();

        $chunks = collect($files)->sum(fn (string $path): int => $this->importer->importUsing(
            $path,
            $chunkingConfiguration,
            $embeddingConfiguration,
        ));

        $result = [
            'chunking_profile' => $chunkingName,
            'embedding_profile' => $embeddingName,
            'documents' => count($files),
            'chunks' => $chunks,
        ];

        Log::info('RAG index rebuilt.', $result);

        return $result;
    }
}
