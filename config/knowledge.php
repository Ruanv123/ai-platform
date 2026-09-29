<?php

return [
    'storage_quota_bytes' => 500 * 1024 * 1024,
    'files_disk' => env('KNOWLEDGE_FILES_DISK', 'local'),
    'pdf_to_text_binary' => env('KNOWLEDGE_PDFTOTEXT_BINARY'),
    'max_file_size_kilobytes' => 20 * 1024,
    'url' => [
        'connect_timeout' => 3,
        'timeout' => 10,
    ],
    'embedding_provider' => env('KNOWLEDGE_EMBEDDING_PROVIDER', 'openai'),
    'embedding_model' => env('KNOWLEDGE_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => 1536,
    'chunking' => [
        'target_tokens' => 800,
        'overlap_tokens' => 100,
        'characters_per_token' => 4,
    ],
    'search' => [
        'top_k' => 5,
        'min_similarity' => 0.35,
    ],
    'product_search' => [
        'candidate_limit' => (int) env('PRODUCT_SEARCH_CANDIDATE_LIMIT', 30),
        'offer_limit' => (int) env('PRODUCT_SEARCH_OFFER_LIMIT', 5),
    ],
];
