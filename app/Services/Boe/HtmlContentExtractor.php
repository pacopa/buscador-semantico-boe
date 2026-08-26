<?php

namespace App\Services\Boe;

use DOMDocument;
use DOMNode;
use DOMXPath;

final class HtmlContentExtractor
{
    /**
     * @param  array<int, string>  $preferredSelectors
     */
    public function extract(string $html, array $preferredSelectors = []): string
    {
        $dom = $this->parse($html);
        $this->removeNonContentNodes($dom);

        foreach ($preferredSelectors as $selector) {
            $text = $this->extractBySelector($dom, $selector);

            if (mb_strlen($text) >= 300) {
                return $text;
            }
        }

        foreach (['main', 'article', '[role="main"]', 'body'] as $selector) {
            $text = $this->extractBySelector($dom, $selector);

            if (mb_strlen($text) >= 300) {
                return $text;
            }
        }

        return $this->normalize($dom->textContent ?? '');
    }

    public function normalize(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function parse(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $payload = '<?xml encoding="UTF-8">' . $html;

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($payload, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function removeNonContentNodes(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        foreach (['script', 'style', 'nav', 'footer', 'header', 'aside', 'noscript'] as $tag) {
            foreach ($xpath->query('//' . $tag) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function extractBySelector(DOMDocument $dom, string $selector): string
    {
        $xpath = new DOMXPath($dom);
        $query = $this->xpathForSelector($selector);

        if ($query === null) {
            return '';
        }

        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMNode) {
                $parts[] = $node->textContent ?? '';
            }
        }

        return $this->normalize(implode(' ', $parts));
    }

    private function xpathForSelector(string $selector): ?string
    {
        if (str_starts_with($selector, '#')) {
            $id = substr($selector, 1);

            return '//*[@id=' . $this->xpathLiteral($id) . ']';
        }

        if (preg_match('/^\[role=["\']?([^"\']+)["\']?\]$/', $selector, $match)) {
            return '//*[@role=' . $this->xpathLiteral($match[1]) . ']';
        }

        if (preg_match('/^[a-z][a-z0-9-]*$/i', $selector)) {
            return '//' . strtolower($selector);
        }

        return null;
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }

        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = array_map(
            fn (string $part): string => "'" . $part . "'",
            explode("'", $value),
        );

        return 'concat(' . implode(', "\'", ', $parts) . ')';
    }
}
