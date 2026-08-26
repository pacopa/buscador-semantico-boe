<?php

namespace Tests\Unit;

use App\Services\Boe\VectorMath;
use PHPUnit\Framework\TestCase;

final class VectorMathTest extends TestCase
{
    public function test_cosine_returns_one_for_identical_vectors(): void
    {
        $this->assertEqualsWithDelta(1.0, VectorMath::cosine([1, 0], [1, 0]), 0.00001);
    }

    public function test_cosine_returns_zero_for_orthogonal_vectors(): void
    {
        $this->assertEqualsWithDelta(0.0, VectorMath::cosine([1, 0], [0, 1]), 0.00001);
    }

    public function test_normalize_preserves_direction_with_unit_length(): void
    {
        $normalized = VectorMath::normalize([3, 4]);

        $this->assertEqualsWithDelta(0.6, $normalized[0], 0.00001);
        $this->assertEqualsWithDelta(0.8, $normalized[1], 0.00001);
    }
}
