<?php

namespace App\Services\Boe;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class BoeIndexer
{
    public function __construct(
        private BoeFetcher $fetcher,
        private TextChunker $chunker,
        private EmbeddingClient $embeddings,
        private ChunkRepository $repository,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            BoeFetcher::fromEnvironment(),
            new TextChunker,
            EmbeddingClient::fromEnvironment(),
            ChunkRepository::fromEnvironment(),
        );
    }

    /** @return array{documents:int,chunks:int,store:string,ingested_documents:int,ingested_chunks:int,skipped_days:int}
     * @throws JsonException
     */
    public function ingest(?string $date = null, bool $sample = false, int $limit = 6, bool $reset = false): array
    {
        return $this->ingestRange($date, $date, $sample, $limit, $reset);
    }

    /** @return array{documents:int,chunks:int,store:string,ingested_documents:int,ingested_chunks:int,skipped_days:int}
     * @throws JsonException
     */
    public function ingestRange(?string $from = null, ?string $to = null, bool $sample = false, int $limit = 6, bool $reset = false): array
    {
        if ($reset) {
            $this->repository->reset();
        }

        $documents = [];
        $skippedDays = 0;
        foreach ($this->datesInRange($from, $to, $sample) as $date) {
            try {
                $documents = [...$documents, ...$this->fetcher->fetch($date, $sample, $limit)];
            } catch (RuntimeException $exception) {
                $skippedDays++;
            }
        }

        if ($documents === []) {
            throw new RuntimeException('No se pudo obtener ningún documento del BOE para el rango indicado.');
        }

        $records = $this->recordsForDocuments($documents);
        $this->repository->saveMany($records);
        $stats = $this->repository->stats();

        return [
            'documents' => $stats['documents'],
            'chunks' => $stats['chunks'],
            'store' => $stats['store'],
            'ingested_documents' => count($documents),
            'ingested_chunks' => count($records),
            'skipped_days' => $skippedDays,
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function datesInRange(?string $from, ?string $to, bool $sample): array
    {
        if ($sample) {
            return [null];
        }

        $start = new DateTimeImmutable($from ?? date('Y-m-d'));
        $end = new DateTimeImmutable($to ?: $start->format('Y-m-d'));

        if ($end < $start) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
        }

        if ($start->diff($end)->days >= 31) {
            throw new InvalidArgumentException('El rango máximo permitido para la demo es de 31 días.');
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * @param  array<int, array{boe_id:string,title:string,date:string,source_url:string,text:string}>  $documents
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function recordsForDocuments(array $documents): array
    {
        $records = [];

        foreach ($documents as $document) {
            $chunks = $this->chunker->chunk($document['text']);
            $texts = array_map(fn (array $chunk): string => $chunk['text'], $chunks);
            $vectors = $this->embeddings->embed($texts);

            foreach ($chunks as $position => $chunk) {
                $records[] = [
                    'id' => $document['boe_id'] . '#' . $chunk['index'],
                    'document_id' => $document['boe_id'],
                    'chunk_index' => $chunk['index'],
                    'text' => $chunk['text'],
                    'embedding' => $vectors[$position] ?? [],
                    'source' => [
                        'boe_id' => $document['boe_id'],
                        'title' => $document['title'],
                        'date' => $document['date'],
                        'url' => $document['source_url'],
                        'position' => [
                            'start' => $chunk['start'],
                            'end' => $chunk['end'],
                        ],
                    ],
                    'created_at' => gmdate(DATE_ATOM),
                ];
            }
        }

        return $records;
    }
}
