<?php

return [
    // simulated: local heuristics, no network. ollama: a local Ollama server or Ollama Cloud.
    'driver' => env('AI_DRIVER', 'simulated'),

    'providers' => [
        'ollama' => [
            // Local: http://localhost:11434 (http://host.docker.internal:11434 from the Docker container).
            // Cloud: https://ollama.com with OLLAMA_API_KEY.
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'api_key' => env('OLLAMA_API_KEY'),
            'model' => env('OLLAMA_MODEL', 'llama3.2'),
            'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
            'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.2),
            // Context window and answer length; larger values are slower on local hardware.
            'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
            'max_tokens' => (int) env('OLLAMA_MAX_TOKENS', 1200),
            // Thinking models (qwen3.5, gpt-oss) answer far faster with reasoning off; the answers are checked anyway.
            'think' => (bool) env('OLLAMA_THINK', false),
        ],
    ],

    // Retrieval over the labor-law notes in resources/knowledge, used to answer settlement questions.
    'rag' => [
        'path' => resource_path('knowledge'),
        // Ollama embedding model for semantic search (e.g. qwen3-embedding:0.6b); empty keeps BM25 only.
        'embedding_model' => env('OLLAMA_EMBED_MODEL', ''),
        'top_k' => (int) env('RAG_TOP_K', 3),
        // A chunk is a candidate when its BM25 score or its cosine similarity reaches these values.
        'min_lexical' => (float) env('RAG_MIN_LEXICAL', 2.0),
        'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.62),
    ],
];
