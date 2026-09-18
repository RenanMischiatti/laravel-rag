<?php

namespace App\Console\Commands;

use App\Services\Rag\RagService;
use Illuminate\Console\Command;

class AskRag extends Command
{
    protected $signature = 'rag:ask {question? : The question to ask}';

    protected $description = 'Answer a question using the imported documents';

    /** Ask a question and display the RAG answer. */
    public function handle(RagService $rag): int
    {
        $question = $this->argument('question');

        if (blank($question)) {
            $this->error('The question cannot be empty.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line($rag->answer($question));

        return self::SUCCESS;
    }
}
