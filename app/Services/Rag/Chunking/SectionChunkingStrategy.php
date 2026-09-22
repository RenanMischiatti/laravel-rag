<?php

namespace App\Services\Rag\Chunking;

use App\Contracts\Rag\ChunkingStrategy;

class SectionChunkingStrategy implements ChunkingStrategy
{
    public function __construct(
        private readonly ParagraphChunkingStrategy $paragraphs,
    ) {}

    /** Split formatted documents by headings and keep the heading in every chunk. */
    public function chunk(string $text, array $options): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        if ($text === '') {
            return [];
        }

        preg_match_all(
            '/(?:^|\n)=+\n(?<title>[^\n]+)\n=+\n(?<content>.*?)(?=\n=+\n[^\n]+\n=+\n|\z)/s',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        if ($matches === []) {
            return $this->paragraphs->chunk($text, $options);
        }

        $chunks = [];
        $firstSectionOffset = $matches[0][0][1];
        $preamble = trim(mb_strcut($text, 0, $firstSectionOffset));

        if ($preamble !== '') {
            array_push($chunks, ...$this->paragraphs->chunk($preamble, $options));
        }

        foreach ($matches as $section) {
            $title = trim($section['title'][0]);
            $content = trim($section['content'][0]);
            $availableSize = max(100, $options['max_characters'] - mb_strlen($title) - 2);
            $sectionChunks = $this->paragraphs->chunk($content, [
                'max_characters' => $availableSize,
            ]);

            foreach ($sectionChunks as $chunk) {
                $chunks[] = $title."\n\n".$chunk;
            }
        }

        return $chunks;
    }
}
