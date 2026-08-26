<?php

namespace App\Services\Boe;

use JsonException;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

final class ChunkRepository
{
    public function __construct(
        private readonly string $store,
        private readonly string $jsonPath,
        private readonly string $mongoUri,
        private readonly string $mongoDatabase,
        private readonly string $mongoCollection = 'chunks',
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            (string) config('boe.data_store', 'json'),
            storage_path((string) config('boe.json_index_path', 'app/boe/chunks.json')),
            (string) config('boe.mongodb.uri', 'mongodb://mongo:27017'),
            (string) config('boe.mongodb.database', 'boe_search'),
            (string) config('boe.mongodb.collection', 'chunks'),
        );
    }

    public function reset(): void
    {
        if ($this->useMongo()) {
            $bulk = new BulkWrite;
            $bulk->delete([]);
            $this->manager()->executeBulkWrite($this->namespace(), $bulk);

            return;
        }

        $this->ensureJsonDirectory();
        file_put_contents($this->jsonPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<int, array<string, mixed>> $chunks
     * @throws JsonException
     */
    public function saveMany(array $chunks): void
    {
        if ($chunks === []) {
            return;
        }

        if ($this->useMongo()) {
            $bulk = new BulkWrite;
            foreach ($chunks as $chunk) {
                $bulk->delete(['id' => $chunk['id']], ['limit' => 0]);
                $bulk->insert($chunk);
            }
            $this->manager()->executeBulkWrite($this->namespace(), $bulk);

            return;
        }

        $indexed = [];
        foreach ($this->all() as $chunk) {
            $indexed[(string) ($chunk['id'] ?? '')] = $chunk;
        }

        foreach ($chunks as $chunk) {
            $indexed[(string) $chunk['id']] = $chunk;
        }

        $this->ensureJsonDirectory();
        file_put_contents(
            $this->jsonPath,
            json_encode(array_values($indexed), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if ($this->useMongo()) {
            $cursor = $this->manager()->executeQuery($this->namespace(), new Query([]));
            $rows = [];

            foreach ($cursor as $row) {
                $decoded = json_decode(json_encode($row, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                unset($decoded['_id']);
                $rows[] = $decoded;
            }

            return $rows;
        }

        if (!file_exists($this->jsonPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->jsonPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{store:string,chunks:int,documents:int,date_from:string|null,date_to:string|null}
     * @throws JsonException
     */
    public function stats(): array
    {
        $chunks = $this->all();
        $documents = [];
        $dates = [];

        foreach ($chunks as $chunk) {
            $documents[(string) ($chunk['source']['boe_id'] ?? $chunk['document_id'] ?? 'unknown')] = true;

            if (isset($chunk['source']['date'])) {
                $dates[] = (string) $chunk['source']['date'];
            }
        }

        sort($dates);

        return [
            'store' => $this->useMongo() ? 'mongodb' : 'json',
            'chunks' => count($chunks),
            'documents' => count($documents),
            'date_from' => $dates[0] ?? null,
            'date_to' => $dates[count($dates) - 1] ?? null,
        ];
    }

    private function useMongo(): bool
    {
        return $this->store === 'mongodb' && extension_loaded('mongodb') && class_exists(Manager::class);
    }

    private function manager(): Manager
    {
        return new Manager($this->mongoUri);
    }

    private function namespace(): string
    {
        return $this->mongoDatabase . '.' . $this->mongoCollection;
    }

    private function ensureJsonDirectory(): void
    {
        $directory = dirname($this->jsonPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}
