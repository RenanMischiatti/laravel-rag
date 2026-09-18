<?php

namespace App\Services\Rag;

class ChunkingService
{
    /**
     * Split a large text into smaller pieces chunks.
     *
     * Each chunk keeps related paragraphs together while staying close to the
     * chosen size limit. Later, each chunk can receive its own embedding and
     * be searched independently without sending the entire document.
     */
    public function chunk(string $text, int $maxCharacters = 1000): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        // An empty document has no content to split.
        if ($text === '') {
            return [];
        }

        // The regular expression finds blank lines, including lines with spaces.
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            // The first paragraph starts the first chunk.
            if ($currentChunk === '') {
                $currentChunk = $paragraph;

                continue;
            }

            $chunkWithNewParagraph = $currentChunk."\n\n".$paragraph;

            // Keep the paragraph in the current chunk when it still fits.
            if (mb_strlen($chunkWithNewParagraph) <= $maxCharacters) {
                $currentChunk = $chunkWithNewParagraph;

                continue;
            }

            // Otherwise, finish the current chunk and start the next one.
            $chunks[] = $currentChunk;
            $currentChunk = $paragraph;
        }

        // The loop only stores full chunks, so the last one is added here.
        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }
}
