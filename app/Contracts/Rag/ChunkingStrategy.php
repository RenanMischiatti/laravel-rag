<?php

namespace App\Contracts\Rag;

interface ChunkingStrategy
{
    /** Split a document using the supplied strategy options. */
    public function chunk(string $text, array $options): array;
}
