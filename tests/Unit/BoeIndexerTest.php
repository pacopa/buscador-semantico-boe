<?php

namespace Tests\Unit;

use App\Services\Boe\BoeIndexer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BoeIndexerTest extends TestCase
{
    public function test_dates_in_range_allows_thirty_one_inclusive_days(): void
    {
        $dates = $this->datesInRange('2026-05-01', '2026-05-31');

        $this->assertCount(31, $dates);
        $this->assertSame('2026-05-01', $dates[0]);
        $this->assertSame('2026-05-31', $dates[30]);
    }

    public function test_dates_in_range_rejects_more_than_thirty_one_inclusive_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El rango máximo permitido para la demo es de 31 días.');

        $this->datesInRange('2026-05-01', '2026-06-01');
    }

    /** @return array<int, string|null> */
    private function datesInRange(string $from, string $to): array
    {
        $reflection = new ReflectionClass(BoeIndexer::class);
        $indexer = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('datesInRange');

        return $method->invoke($indexer, $from, $to, false);
    }
}
