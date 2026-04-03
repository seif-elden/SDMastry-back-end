<?php

return [
    'chroma_base_url' => env('CHROMA_BASE_URL', 'http://localhost:8002'),
    'collection_books' => env('CHROMA_COLLECTION_BOOKS', 'sdmastery_books'),
    'collection_answers' => env('CHROMA_COLLECTION_ANSWERS', 'sdmastery_model_answers'),

    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://192.168.1.113:11434'),
    'ollama_embedder' => env('OLLAMA_EMBEDDER', 'nomic-embed-text'),

    'context_chunks' => (int) env('EVALUATION_CONTEXT_CHUNKS', 5),
    'answer_chunks' => (int) env('EVALUATION_ANSWER_CHUNKS', 3),

    'ingest_batch_size' => 20,
    'default_chunk_size' => 512,
    'default_chunk_overlap' => 50,
];
