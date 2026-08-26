<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Boe\HashEmbedding;
use App\Services\Boe\TextChunker;
use App\Services\Boe\VectorMath;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

assert_true(abs(VectorMath::cosine([1, 0], [1, 0]) - 1.0) < 0.00001, 'cosine returns 1.0 for identical vectors');
assert_true(abs(VectorMath::cosine([1, 0], [0, 1])) < 0.00001, 'cosine returns 0.0 for orthogonal vectors');

$chunks = (new TextChunker)->chunk(str_repeat('Article 1. Public grants for green industry. ', 80), 300, 40);
assert_true(count($chunks) > 1, 'chunker splits long text into multiple chunks');
assert_true($chunks[0]['index'] === 0, 'chunker assigns zero-based indexes');

$embedder = new HashEmbedding;
[$a, $b, $c] = $embedder->embed([
    'green hydrogen industrial grants',
    'green hydrogen industrial grants',
    'public employment appointments',
]);
assert_true(count($a) === HashEmbedding::DIMENSIONS, 'hash fallback emits expected vector dimensions');
assert_true(VectorMath::cosine($a, $b) > VectorMath::cosine($a, $c), 'embedding fallback ranks identical text above unrelated text');

fwrite(STDOUT, "Core validation completed.\n");
