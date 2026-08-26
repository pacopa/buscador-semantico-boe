<?php

namespace App\Services\Boe;

final class VectorMath
{
    /**
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $limit = min(count($a), count($b));

        if ($limit === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $limit; $i++) {
            $av = (float) $a[$i];
            $bv = (float) $b[$i];
            $dot += $av * $bv;
            $normA += $av * $av;
            $normB += $bv * $bv;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @param  array<int, float|int>  $vector
     * @return array<int, float>
     */
    public static function normalize(array $vector): array
    {
        $norm = sqrt(array_reduce(
            $vector,
            fn (float $carry, float|int $value): float => $carry + ((float) $value * (float) $value),
            0.0,
        ));

        if ($norm <= 0.0) {
            return array_map(fn (): float => 0.0, $vector);
        }

        return array_map(fn (float|int $value): float => (float) $value / $norm, $vector);
    }
}
