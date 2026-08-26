<?php

namespace App\Services\Boe;

use RuntimeException;

final readonly class BoeFetcher
{
    private const CACHE_VERSION = 1;

    public function __construct(
        private string $fixturePath,
        private string $cacheDirectory,
        private HtmlContentExtractor $contentExtractor = new HtmlContentExtractor,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            storage_path((string) config('boe.fixture_path', 'app/fixtures/boe-sample.json')),
            storage_path('app/boe/boe-documents'),
        );
    }

    /**
     * @return array<int, array{boe_id:string,title:string,date:string,source_url:string,text:string}>
     */
    public function fetch(?string $date = null, bool $sample = false, int $limit = 6): array
    {
        if ($sample) {
            return $this->fromFixture();
        }

        return $this->fromBoeWebsite($date ?? date('Y-m-d'), $limit);
    }

    /**
     * @return array<int, array{boe_id:string,title:string,date:string,source_url:string,text:string}>
     */
    public function fromFixture(): array
    {
        if (!file_exists($this->fixturePath)) {
            throw new RuntimeException('Fixture file not found at ' . $this->fixturePath);
        }

        $decoded = json_decode((string) file_get_contents($this->fixturePath), true);

        if (!is_array($decoded) || !isset($decoded['documents']) || !is_array($decoded['documents'])) {
            throw new RuntimeException('Invalid BOE fixture payload.');
        }

        return $decoded['documents'];
    }

    /**
     * @return array<int, array{boe_id:string,title:string,date:string,source_url:string,text:string}>
     */
    private function fromBoeWebsite(string $date, int $limit): array
    {
        [$year, $month, $day] = explode('-', $date);
        $indexUrl = sprintf('https://www.boe.es/boe/dias/%s/%s/%s/index.php?lang=es', $year, $month, $day);
        $html = $this->httpGet($indexUrl);

        preg_match_all('/href="(?<href>[^"]*txt\.php\?id=(?<id>BOE-[A-Z]-[0-9-]+)[^"]*)"/i', $html, $matches, PREG_SET_ORDER);
        $seen = [];
        $documents = [];

        foreach ($matches as $match) {
            $boeId = $match['id'];

            if (isset($seen[$boeId])) {
                continue;
            }

            $seen[$boeId] = true;
            $sourceUrl = 'https://www.boe.es' . html_entity_decode($match['href']);
            $document = $this->fetchDocument($boeId, $sourceUrl, $date);

            if (mb_strlen($document['text']) < 300) {
                continue;
            }

            $documents[] = $document;

            if (count($documents) >= $limit) {
                break;
            }
        }

        if ($documents === []) {
            throw new RuntimeException('No BOE documents parsed from ' . $indexUrl);
        }

        return $documents;
    }

    /** @return array{boe_id:string,title:string,date:string,source_url:string,text:string} */
    private function fetchDocument(string $boeId, string $sourceUrl, string $date): array
    {
        $cached = $this->cachedDocument($boeId);

        if ($cached !== null) {
            return $cached;
        }

        $documentHtml = $this->httpGet($sourceUrl);
        $document = [
            'boe_id' => $boeId,
            'title' => $this->extractTitle($documentHtml, $boeId),
            'date' => $date,
            'source_url' => $sourceUrl,
            'text' => $this->extractText($documentHtml),
        ];

        $this->cacheDocument($boeId, $document);

        return $document;
    }

    /** @return array{boe_id:string,title:string,date:string,source_url:string,text:string}|null */
    private function cachedDocument(string $boeId): ?array
    {
        $path = $this->cachePath($boeId);

        if (!file_exists($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!$this->isValidCachedDocument($decoded) || $decoded['boe_id'] !== $boeId) {
            @unlink($path);

            return null;
        }

        return [
            'boe_id' => $decoded['boe_id'],
            'title' => $decoded['title'],
            'date' => $decoded['date'],
            'source_url' => $decoded['source_url'],
            'text' => $decoded['text'],
        ];
    }

    private function isValidCachedDocument(mixed $decoded): bool
    {
        if (!is_array($decoded) || ($decoded['cache_version'] ?? null) !== self::CACHE_VERSION) {
            return false;
        }

        foreach (['boe_id', 'title', 'date', 'source_url', 'text'] as $key) {
            if (!isset($decoded[$key]) || !is_string($decoded[$key])) {
                return false;
            }
        }

        return true;
    }

    /** @param array{boe_id:string,title:string,date:string,source_url:string,text:string} $document */
    private function cacheDocument(string $boeId, array $document): void
    {
        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0775, true);
        }

        file_put_contents($this->cachePath($boeId), json_encode([
            ...$document,
            'cache_version' => self::CACHE_VERSION,
            'fetched_at' => gmdate(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function cachePath(string $boeId): string
    {
        return $this->cacheDirectory . '/' . preg_replace('/[^A-Z0-9-]/i', '_', $boeId) . '.json';
    }

    private function httpGet(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: buscador-semantico-boe/1.0\r\nAccept: text/html,application/xhtml+xml\r\n",
                'timeout' => 20,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException('Could not fetch ' . $url);
        }

        return $response;
    }

    private function extractTitle(string $html, string $fallback): string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $match)) {
            return $this->cleanHtml($match[1]);
        }

        return $fallback;
    }

    private function extractText(string $html): string
    {
        return $this->contentExtractor->extract($html, ['#textoxslt']);
    }

    private function cleanHtml(string $html): string
    {
        return $this->contentExtractor->normalize($html);
    }
}
