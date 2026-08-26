<?php

namespace App\Services\Boe;

final class HashEmbedding
{
    public const int DIMENSIONS = 384;

    /**
     * Deterministic local fallback used only when the FastAPI embedding sidecar is unavailable.
     * It keeps offline demos and validation scripts working without paid APIs.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts, int $dimensions = self::DIMENSIONS): array
    {
        return array_map(fn (string $text): array => $this->embedOne($text, $dimensions), $texts);
    }

    /** @return array<int, float> */
    private function embedOne(string $text, int $dimensions): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $digest = hash('sha256', $token, true);
            $hash = unpack('N', substr($digest, 0, 4))[1];
            $index = $hash % $dimensions;
            $sign = (ord($digest[4]) % 2) === 1 ? 1.0 : -1.0;
            $vector[$index] += $sign;
        }

        return VectorMath::normalize($vector);
    }
}
