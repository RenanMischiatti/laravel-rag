<?php

namespace App\Console\Commands;

use App\Services\Rag\DocumentImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EmbedDocuments extends Command
{
    protected $signature = 'embed:documents';

    protected $description = 'Import TXT documents, split them into chunks, and generate embeddings';

    /** Run the import process. */
    public function handle(DocumentImportService $importer): int
    {
        $files = File::glob(base_path('documents/*.txt'));

        if ($files === []) {
            $this->warn('No .txt files were found in documents/.');
            return self::SUCCESS;
        }

        collect($files)->each(function (string $path) use ($importer): void {
            $this->line('Importing '.basename($path).'...');

            $chunks = $importer->import(
                $path,
                config('rag.defaults.chunking'),
                config('rag.defaults.embedding'),
            );

            $this->info(basename($path).": $chunks chunk(s) saved.");
        });

        return self::SUCCESS;
    }
}
