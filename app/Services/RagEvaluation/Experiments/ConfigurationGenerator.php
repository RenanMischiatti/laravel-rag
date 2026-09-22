<?php

namespace App\Services\RagEvaluation\Experiments;

use Illuminate\Support\Collection;

class ConfigurationGenerator
{
    /** Expand every chunking strategy option into independent configurations. */
    public function chunking(array $strategies): Collection
    {
        return collect($strategies)->flatMap(function (array $strategy, string $name): Collection {
            return $this->cartesian($strategy['options'])->map(fn (array $options): array => [
                'name' => $this->name($name, $options),
                'configuration' => [
                    'strategy' => $strategy['strategy'],
                    'options' => $options,
                ],
            ]);
        })->values();
    }

    /** Expand every embedding model and input mode pair. */
    public function embeddings(array $profiles): Collection
    {
        return collect($profiles)->flatMap(function (array $profile, string $name): Collection {
            return collect($profile['input_modes'])->map(
                fn (array $mode, string $modeName): array => [
                    'name' => "{$name}:{$modeName}",
                    'configuration' => [
                        'model' => $profile['model'],
                        'dimensions' => $profile['dimensions'],
                        ...$mode,
                    ],
                ],
            );
        })->values();
    }

    /** Calculate the cartesian product of named option values. */
    private function cartesian(array $options): Collection
    {
        return collect($options)->reduce(
            function (Collection $combinations, array $values, string $key): Collection {
                return $combinations->flatMap(
                    fn (array $combination): Collection => collect($values)->map(
                        fn (mixed $value): array => [...$combination, $key => $value],
                    ),
                );
            },
            collect([[]]),
        );
    }

    /** Build a readable configuration identifier. */
    private function name(string $strategy, array $options): string
    {
        $values = collect($options)
            ->map(fn (mixed $value, string $key): string => "{$key}={$value}")
            ->implode(',');

        return "{$strategy}({$values})";
    }
}
