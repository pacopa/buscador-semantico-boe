<?php

namespace App\Services\Boe;

use JsonException;
use RuntimeException;

final readonly class EmbeddingClient
{
    private const BATCH_SIZE = 64;

    public function __construct(
        private string $baseUrl,
        private bool $allowFallback = true,
        private HashEmbedding $fallback = new HashEmbedding,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            rtrim((string) config('boe.embeddings.service_url', 'http://embeddings:8000'), '/'),
            (bool) config('boe.embeddings.allow_hash_fallback', true),
        );
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     *
     * @throws JsonException
     */
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        try {
            $embeddings = [];

            foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
                foreach ($this->requestService($batch) as $embedding) {
                    $embeddings[] = $embedding;
                }
            }

            return $embeddings;
        } catch (RuntimeException $exception) {
            if (!$this->allowFallback) {
                throw $exception;
            }

            return $this->fallback->embed($texts);
        }
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     *
     * @throws JsonException
     */
    private function requestService(array $texts): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Embedding service URL is not configured.');
        }

        $payload = json_encode(['texts' => array_values($texts)], JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->baseUrl . '/embed', false, $context);

        if ($response === false) {
            throw new RuntimeException('Embedding service is not reachable.');
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !isset($decoded['embeddings']) || !is_array($decoded['embeddings'])) {
            throw new RuntimeException('Embedding service returned an invalid payload: ' . $response);
        }

        if (count($decoded['embeddings']) !== count($texts)) {
            throw new RuntimeException('Embedding service returned a different number of vectors than requested.');
        }

        return array_map(
            function (mixed $embedding): array {
                if (!is_array($embedding)) {
                    throw new RuntimeException('Embedding service returned a malformed vector.');
                }

                return array_map(
                    fn (mixed $value): float => is_numeric($value)
                        ? (float) $value
                        : throw new RuntimeException('Embedding service returned a non-numeric vector value.'),
                    $embedding,
                );
            },
            $decoded['embeddings'],
        );
    }
}
