<?php

namespace Tests\Unit;

use App\Services\Boe\SemanticSearch;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SemanticSearchTest extends TestCase
{
    public function test_single_word_query_discards_low_score_without_literal_match(): void
    {
        $this->assertFalse($this->isRelevant('guerra', 0.134, []));
    }

    public function test_single_word_query_accepts_relevant_result_with_literal_evidence(): void
    {
        $this->assertTrue($this->isRelevant('guerra', 0.300, ['guerra']));
    }

    public function test_short_single_word_query_still_requires_literal_evidence(): void
    {
        $this->assertFalse($this->isRelevant('IVA', 0.300, []));
        $this->assertFalse($this->isRelevant('ley', 0.300, []));
    }

    public function test_specific_phrase_discards_medium_score_without_literal_evidence(): void
    {
        $this->assertFalse($this->isRelevant('presidente del gobierno', 0.450, []));
    }

    public function test_specific_phrase_discards_partial_literal_evidence(): void
    {
        $this->assertFalse($this->isRelevant('presidente del gobierno', 0.394, ['presidente']));
    }

    public function test_specific_phrase_accepts_complete_literal_evidence(): void
    {
        $this->assertTrue($this->isRelevant('presidente del gobierno', 0.394, ['presidente', 'gobierno']));
    }

    public function test_very_high_semantic_score_can_pass_without_literal_evidence(): void
    {
        $this->assertTrue($this->isRelevant('ayudas agrarias', 0.720, []));
    }

    public function test_exact_phrase_scores_above_separate_terms(): void
    {
        $separateTermsScore = $this->score('presidente del gobierno', 0.374, 1.0, 0.0);
        $exactPhraseScore = $this->score('presidente del gobierno', 0.238, 1.0, 1.0);

        $this->assertGreaterThan($separateTermsScore, $exactPhraseScore);
    }

    public function test_phrase_match_ignores_stopwords_but_requires_ordered_adjacency(): void
    {
        $this->assertSame(1.0, $this->phraseMatch('presidente del gobierno', 'El Presidente del Gobierno comparece.'));
        $this->assertSame(0.0, $this->phraseMatch('presidente del gobierno', 'El Presidente del Tribunal informa al Gobierno.'));
    }

    public function test_phrase_match_does_not_cross_title_and_text_boundaries(): void
    {
        $this->assertSame(0.0, $this->phraseMatchInFields('presidente del gobierno', 'Real Decreto por el que se nombra Presidente', 'Gobierno autonómico'));
    }

    public function test_short_terms_can_be_used_as_literal_evidence(): void
    {
        $this->assertSame(['iva'], $this->matchedTerms('IVA', 'Regulación del IVA reducido.'));
        $this->assertSame(['ley'], $this->matchedTerms('ley', 'Nueva ley autonómica.'));
    }

    public function test_title_terms_can_be_used_as_literal_evidence(): void
    {
        $terms = $this->matchedTerms(
            'guerra',
            'Medidas urgentes por la guerra en Ucrania Texto administrativo sin la palabra clave.',
        );

        $this->assertSame(['guerra'], $terms);
    }

    /** @param array<int, string> $matchedTerms */
    private function isRelevant(string $interest, float $score, array $matchedTerms): bool
    {
        $reflection = new ReflectionClass(SemanticSearch::class);
        $search = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isRelevant');

        return $method->invoke($search, $interest, $score, $matchedTerms);
    }

    private function score(string $interest, float $semanticScore, float $lexicalScore, float $phraseScore): float
    {
        $reflection = new ReflectionClass(SemanticSearch::class);
        $search = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('score');

        return $method->invoke($search, $interest, $semanticScore, $lexicalScore, $phraseScore);
    }

    private function phraseMatch(string $interest, string $text): float
    {
        $reflection = new ReflectionClass(SemanticSearch::class);
        $search = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('phraseMatch');

        return $method->invoke($search, $interest, $text);
    }

    private function phraseMatchInFields(string $interest, string ...$fields): float
    {
        $reflection = new ReflectionClass(SemanticSearch::class);
        $search = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('phraseMatchInFields');

        return $method->invoke($search, $interest, ...$fields);
    }

    /** @return array<int, string> */
    private function matchedTerms(string $interest, string $text): array
    {
        $reflection = new ReflectionClass(SemanticSearch::class);
        $search = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('matchedTerms');

        return $method->invoke($search, $interest, $text);
    }
}
