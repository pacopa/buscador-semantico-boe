<?php

namespace Tests\Unit;

use App\Services\Boe\EmbeddingClient;
use PHPUnit\Framework\TestCase;

final class EmbeddingClientTest extends TestCase
{
    public function test_fallback_embedding_preserves_count_for_large_batches(): void
    {
        $client = new EmbeddingClient('', true);
        $texts = array_fill(0, 130, 'texto de prueba');

        $embeddings = $client->embed($texts);

        $this->assertCount(130, $embeddings);
    }
}
