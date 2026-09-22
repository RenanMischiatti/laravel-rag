<?php

namespace App\Services\Rag\Chunking;

class SectionSemanticChunkingStrategy extends SemanticChunkingStrategy
{
    /** Preserve section titles and group related units inside each section. */
    public function chunk(string $text, array $options): array
    {
        $sections = $this->sections($text);

        if ($sections === []) {
            return parent::chunk($text, $options);
        }

        $units = collect($sections)->flatMap(
            fn (array $section, int $position): array => $this->units(
                $section['content'],
                "section-{$position}",
                $section['title'],
                $options,
            ),
        )->all();

        return $this->groupUnits($units, $options);
    }

    /** Extract document sections marked by separator lines. */
    private function sections(string $text): array
    {
        preg_match_all(
            '/(?:^|\n)=+\n(?<title>[^\n]+)\n=+\n(?<content>.*?)(?=\n=+\n[^\n]+\n=+\n|\z)/s',
            trim(str_replace(["\r\n", "\r"], "\n", $text)),
            $matches,
            PREG_SET_ORDER,
        );

        return collect($matches)->map(fn (array $section): array => [
            'title' => trim($section['title']),
            'content' => trim($section['content']),
        ])->all();
    }

    /** Convert one section into units that share its title and boundary. */
    private function units(string $text, string $group, string $title, array $options): array
    {
        $availableSize = max(100, $options['max_characters'] - mb_strlen($title) - 2);

        return collect($this->splitUnits($text, $availableSize))
            ->map(fn (string $content): array => [
                'content' => $content,
                'embedding_content' => $title === '' ? $content : $title."\n\n".$content,
                'group' => $group,
                'prefix' => $title,
            ])
            ->all();
    }
}
