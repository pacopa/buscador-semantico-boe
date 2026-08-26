<?php

namespace Tests\Unit;

use App\Services\Boe\BoeFetcher;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BoeFetcherTest extends TestCase
{
    public function test_cached_document_requires_expected_schema(): void
    {
        $this->assertTrue($this->isValidCachedDocument([
            'cache_version' => 1,
            'boe_id' => 'BOE-A-2026-1',
            'title' => 'Documento',
            'date' => '2026-05-01',
            'source_url' => 'https://www.boe.es/',
            'text' => 'Contenido',
        ]));
    }

    public function test_cached_document_rejects_missing_or_stale_schema(): void
    {
        $this->assertFalse($this->isValidCachedDocument([
            'boe_id' => 'BOE-A-2026-1',
            'title' => 'Documento',
            'date' => '2026-05-01',
            'source_url' => 'https://www.boe.es/',
            'text' => 'Contenido',
        ]));

        $this->assertFalse($this->isValidCachedDocument([
            'cache_version' => 1,
            'boe_id' => 'BOE-A-2026-1',
            'title' => 'Documento',
            'date' => '2026-05-01',
            'source_url' => 'https://www.boe.es/',
        ]));
    }

    private function isValidCachedDocument(mixed $payload): bool
    {
        $reflection = new ReflectionClass(BoeFetcher::class);
        $fetcher = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isValidCachedDocument');

        return $method->invoke($fetcher, $payload);
    }
}
