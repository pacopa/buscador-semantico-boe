<?php

return [
    'data_store' => env('BOE_DATA_STORE', 'json'),
    'json_index_path' => env('BOE_JSON_INDEX_PATH', 'app/boe/chunks.json'),
    'fixture_path' => env('BOE_FIXTURE_PATH', 'app/fixtures/boe-sample.json'),

    'mongodb' => [
        'uri' => env('BOE_MONGODB_URI', 'mongodb://mongo:27017'),
        'database' => env('BOE_MONGODB_DATABASE', 'boe_search'),
        'collection' => env('BOE_MONGODB_COLLECTION', 'chunks'),
    ],

    'embeddings' => [
        'service_url' => env('BOE_EMBEDDING_SERVICE_URL', 'http://embeddings:8000'),
        'allow_hash_fallback' => (bool) env('BOE_EMBEDDING_ALLOW_HASH_FALLBACK', true),
    ],
];
