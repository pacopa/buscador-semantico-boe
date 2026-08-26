<?php

namespace App\Services\Boe;

final class TextChunker
{
    /**
     * @return array<int, array{index:int,text:string,start:int,end:int}>
     */
    public function chunk(string $text, int $maxCharacters = 1400, int $overlap = 180): array
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($normalized === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($normalized);
        $cursor = 0;
        $index = 0;

        while ($cursor < $length) {
            $targetEnd = min($cursor + $maxCharacters, $length);
            $end = $this->findSentenceBoundary($normalized, $cursor, $targetEnd);

            if ($end <= $cursor) {
                $end = $targetEnd;
            }

            $textPart = trim(mb_substr($normalized, $cursor, $end - $cursor));

            if ($textPart !== '') {
                $chunks[] = [
                    'index' => $index++,
                    'text' => $textPart,
                    'start' => $cursor,
                    'end' => $end,
                ];
            }

            if ($end >= $length) {
                break;
            }

            $nextCursor = max(0, $end - $overlap);
            $cursor = $nextCursor > $cursor ? $nextCursor : $end;
        }

        return $chunks;
    }

    private function findSentenceBoundary(string $text, int $start, int $targetEnd): int
    {
        $window = mb_substr($text, $start, $targetEnd - $start);
        $best = -1;

        foreach (['. ', '; ', ': ', '\n'] as $marker) {
            $position = mb_strrpos($window, $marker);

            if ($position !== false && $position > $best) {
                $best = $position + mb_strlen($marker);
            }
        }

        return $best > 0 ? $start + $best : $targetEnd;
    }
}
