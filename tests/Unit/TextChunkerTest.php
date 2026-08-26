<?php

namespace Tests\Unit;

use App\Services\Boe\TextChunker;
use PHPUnit\Framework\TestCase;

final class TextChunkerTest extends TestCase
{
    public function test_empty_text_returns_no_chunks(): void
    {
        $this->assertSame([], (new TextChunker)->chunk('   '));
    }

    public function test_long_text_is_split_with_sequential_indexes(): void
    {
        $chunks = (new TextChunker)->chunk(str_repeat('Artículo 1. Ayudas públicas para industria verde. ', 40), 220, 40);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertSame(0, $chunks[0]['index']);
        $this->assertSame(1, $chunks[1]['index']);
    }

    public function test_chunker_prefers_sentence_boundaries(): void
    {
        $chunks = (new TextChunker)->chunk('Primera frase. Segunda frase con contenido relevante. Tercera frase.', 35, 0);

        $this->assertSame('Primera frase.', $chunks[0]['text']);
    }
}
