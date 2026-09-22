<?php

use App\Services\Rag\Chunking\FixedTextChunkingStrategy;
use App\Services\Rag\Chunking\ParagraphChunkingStrategy;
use App\Services\Rag\Chunking\SectionChunkingStrategy;
use App\Services\Rag\Chunking\SectionSemanticChunkingStrategy;
use App\Services\Rag\Chunking\SemanticChunkingStrategy;
use App\Services\Rag\Retrieval\HybridRetrievalStrategy;
use App\Services\Rag\Retrieval\VectorRetrievalStrategy;

return [
    'chunking' => [
        'paragraph' => ParagraphChunkingStrategy::class,
        'fixed' => FixedTextChunkingStrategy::class,
        'section' => SectionChunkingStrategy::class,
        'semantic' => SemanticChunkingStrategy::class,
        'section_semantic' => SectionSemanticChunkingStrategy::class,
    ],

    'embedding' => [
        'nomic_768' => [
            'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text-v2-moe'),
            'dimensions' => 768,
            'input_modes' => [
                'asymmetric' => [
                    'document_prefix' => 'search_document: ',
                    'query_prefix' => 'search_query: ',
                ],
                'plain' => [
                    'document_prefix' => '',
                    'query_prefix' => '',
                ],
            ],
        ],
    ],

    'retrieval' => [
        'vector' => [
            'strategy' => VectorRetrievalStrategy::class,
        ],
        'hybrid' => [
            'strategy' => HybridRetrievalStrategy::class,
            'candidate_limit' => 20,
            'rrf_k' => 60,
        ],
    ],
];
