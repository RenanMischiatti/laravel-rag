<?php

return [
    'default_for_embeddings' => 'ollama',

    'caching' => [
        'embeddings' => [
            'cache' => true,
            'store' => env('CACHE_STORE', 'database'),
            'individually' => true,
        ],
    ],

    'providers' => [
        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://ollama:11434'),
            'models' => [
                'embeddings' => [
                    'default' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text-v2-moe'),
                    'dimensions' => 768,
                ],
            ],
        ],
    ],
];
