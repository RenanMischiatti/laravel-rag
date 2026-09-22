<?php

namespace App\Console\Commands;

use App\Services\Rag\DocumentIndexService;
use Illuminate\Console\Command;

class EmbedDocuments extends Command
{
    protected $signature = 'embed:documents';

    protected $description = 'Import TXT documents, split them into chunks, and generate embeddings';

    /** Run the import process. */
    public function handle(DocumentIndexService $indexer): int
    {
        $result = $indexer->rebuildCurrent();

        $this->info("{$result['documents']} document(s) imported with {$result['chunks']} chunk(s).");

        return self::SUCCESS;
    }
}
