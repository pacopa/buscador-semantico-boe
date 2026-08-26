<?php

namespace App\Services\Boe;

use JsonException;

final readonly class SemanticSearch
{
    private const MINIMUM_SCORE = 0.25;

    private const MINIMUM_SCORE_WITHOUT_LITERAL_EVIDENCE = 0.70;

    /** @var array<int, string> */
    private const STOPWORDS = [
        'con',
        'del',
        'las',
        'los',
        'para',
        'por',
        'que',
        'una',
        'uno',
    ];

    public function __construct(
        private ChunkRepository $repository,
        private EmbeddingClient $embeddings,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(ChunkRepository::fromEnvironment(), EmbeddingClient::fromEnvironment());
    }

    /**
     * @return array<int, array{score:float,snippet:string,justification:string,source:array<string,mixed>,chunk:array<string,mixed>,matched_terms:array<int, string>}>
     *
     * @throws JsonException
     */
    public function match(string $interest, int $limit = 8): array
    {
        $queryVector = $this->embeddings->embed([$interest])[0] ?? [];
        $rows = [];

        foreach ($this->repository->all() as $chunk) {
            $semanticScore = VectorMath::cosine($queryVector, $chunk['embedding'] ?? []);
            $text = (string) ($chunk['text'] ?? '');
            $source = is_array($chunk['source'] ?? null) ? $chunk['source'] : [];
            $title = (string) ($source['title'] ?? '');
            $haystack = $title . ' ' . $text;
            $matchedTerms = $this->matchedTerms($interest, $haystack);
            $lexicalScore = $this->lexicalOverlap($interest, $matchedTerms);
            $phraseScore = $this->phraseMatchInFields($interest, $title, $text);
            $score = $this->score($interest, $semanticScore, $lexicalScore, $phraseScore);

            if (!$this->isRelevant($interest, $score, $matchedTerms)) {
                continue;
            }

            $snippet = $this->snippet($text, $interest);

            $rows[] = [
                'score' => $score,
                'snippet' => $snippet,
                'justification' => $this->justify($interest, $score, $semanticScore, $lexicalScore, $phraseScore, $matchedTerms),
                'source' => $source,
                'chunk' => $chunk,
                'matched_terms' => $matchedTerms,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($rows, 0, $limit);
    }

    private function snippet(string $text, string $interest): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= 420) {
            return $text;
        }

        $interestTokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($interest)) ?: [];
        $bestPosition = 0;

        foreach ($interestTokens as $token) {
            if (mb_strlen($token) < 4) {
                continue;
            }

            $position = mb_stripos($text, $token);
            if ($position !== false) {
                $bestPosition = max(0, $position - 160);
                break;
            }
        }

        return trim(mb_substr($text, $bestPosition, 420)) . '…';
    }

    private function score(string $interest, float $semanticScore, float $lexicalScore, float $phraseScore): float
    {
        if (count($this->tokens($interest)) < 2) {
            return (0.82 * $semanticScore) + (0.18 * $lexicalScore);
        }

        return (0.55 * $semanticScore) + (0.25 * $lexicalScore) + (0.20 * $phraseScore);
    }

    /** @param array<int, string> $matchedTerms */
    private function lexicalOverlap(string $interest, array $matchedTerms): float
    {
        $queryTokens = $this->tokens($interest);

        if ($queryTokens === []) {
            return 0.0;
        }

        return count($matchedTerms) / count($queryTokens);
    }

    /** @param array<int, string> $matchedTerms */
    private function isRelevant(string $interest, float $score, array $matchedTerms): bool
    {
        $queryTokens = $this->tokens($interest);

        if ($matchedTerms === []) {
            return $score >= self::MINIMUM_SCORE_WITHOUT_LITERAL_EVIDENCE;
        }

        if (count($queryTokens) <= 3 && count($matchedTerms) < count($queryTokens)) {
            return $score >= self::MINIMUM_SCORE_WITHOUT_LITERAL_EVIDENCE;
        }

        return $score >= self::MINIMUM_SCORE;
    }

    private function phraseMatchInFields(string $interest, string ...$fields): float
    {
        $scores = array_map(
            fn (string $field): float => $this->phraseMatch($interest, $field),
            $fields,
        );

        return max($scores ?: [0.0]);
    }

    private function phraseMatch(string $interest, string $text): float
    {
        $queryTokens = $this->tokenSequence($interest);
        $textTokens = $this->tokenSequence($text);
        $queryLength = count($queryTokens);

        if ($queryLength < 2 || count($textTokens) < $queryLength) {
            return 0.0;
        }

        $lastStart = count($textTokens) - $queryLength;
        for ($start = 0; $start <= $lastStart; $start++) {
            if (array_slice($textTokens, $start, $queryLength) === $queryTokens) {
                return 1.0;
            }
        }

        return 0.0;
    }

    /** @return array<int, string> */
    private function matchedTerms(string $interest, string $text): array
    {
        $textTokens = array_flip($this->tokens($text));

        return array_values(array_filter(
            $this->tokens($interest),
            fn (string $token): bool => isset($textTokens[$token]),
        ));
    }

    /** @return array<int, string> */
    private function tokens(string $text): array
    {
        return array_values(array_unique($this->tokenSequence($text)));
    }

    /** @return array<int, string> */
    private function tokenSequence(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [],
            fn (string $token): bool => mb_strlen($token) >= 3 && !in_array($token, self::STOPWORDS, true),
        ));
    }

    /** @param array<int, string> $matchedTerms */
    private function justify(string $interest, float $score, float $semanticScore, float $lexicalScore, float $phraseScore, array $matchedTerms): string
    {
        $level = match (true) {
            $score >= 0.65 => 'Coincidencia semántica alta',
            $score >= 0.40 => 'Coincidencia semántica media',
            default => 'Coincidencia semántica baja',
        };

        $terms = $matchedTerms === []
            ? 'sin coincidencias literales directas'
            : 'términos coincidentes: ' . implode(', ', $matchedTerms);

        return sprintf(
            '%s para el interés "%s": el fragmento combina similitud semántica (%.3f), coincidencia directa de términos (%.3f; %s) y coincidencia de frase (%.3f), para una puntuación final de %.3f.',
            $level,
            $interest,
            $semanticScore,
            $lexicalScore,
            $terms,
            $phraseScore,
            $score,
        );
    }
}
