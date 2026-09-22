<?php

namespace App\Services\Rag\Chunking;

use App\Contracts\Rag\ChunkingStrategy;

class ParagraphChunkingStrategy implements ChunkingStrategy
{
    /** Group complete paragraphs until the configured size is reached. */
    public function chunk(string $text, array $options): array
    {
        $maxCharacters = $options['max_characters'];
        $paragraphs = preg_split('/\n\s*\n/', $this->normalize($text)) ?: [];
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $candidate = $currentChunk === ''
                ? $paragraph
                : $currentChunk."\n\n".$paragraph;

            if ($currentChunk !== '' && mb_strlen($candidate) > $maxCharacters) {
                $chunks[] = $currentChunk;
                $currentChunk = $paragraph;

                continue;
            }

            $currentChunk = $candidate;
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /** Normalize line endings before splitting. */
    private function normalize(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
