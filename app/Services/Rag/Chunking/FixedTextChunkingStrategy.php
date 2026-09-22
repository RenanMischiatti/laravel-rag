<?php

namespace App\Services\Rag\Chunking;

use App\Contracts\Rag\ChunkingStrategy;
use InvalidArgumentException;

class FixedTextChunkingStrategy implements ChunkingStrategy
{
    /** Split text by character size and preserve a configurable overlap. */
    public function chunk(string $text, array $options): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $size = $options['max_characters'];
        $overlap = $options['overlap'];

        if ($overlap >= $size) {
            throw new InvalidArgumentException('Chunk overlap must be smaller than chunk size.');
        }

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $step = $size - $overlap;

        for ($offset = 0; $offset < mb_strlen($text); $offset += $step) {
            $chunk = trim(mb_substr($text, $offset, $size));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($offset + $size >= mb_strlen($text)) {
                break;
            }
        }

        return $chunks;
    }
}
