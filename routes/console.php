<?php

use App\Services\Boe\BoeIndexer;
use App\Services\Boe\ChunkRepository;
use App\Services\Boe\SemanticSearch;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('boe:ingest {--date=} {--from=} {--to=} {--sample} {--replace} {--limit=5}', function (): int {
    $from = $this->option('from') ?: $this->option('date') ?: null;
    $to = $this->option('to') ?: $from;
    $stats = BoeIndexer::fromEnvironment()->ingestRange(
        $from,
        $to,
        (bool) $this->option('sample'),
        (int) $this->option('limit'),
        (bool) $this->option('replace'),
    );

    $skippedMessage = $stats['skipped_days'] > 0
        ? sprintf(' Días omitidos sin documentos disponibles: %d.', $stats['skipped_days'])
        : '';

    $this->info(sprintf(
        'Índice actualizado: %d fragmentos añadidos/actualizados de %d documentos procesados. Total actual: %d fragmentos de %d documentos en almacenamiento %s.%s',
        $stats['ingested_chunks'],
        $stats['ingested_documents'],
        $stats['chunks'],
        $stats['documents'],
        $stats['store'],
        $skippedMessage,
    ));

    return Command::SUCCESS;
})->purpose('Descarga documentos del BOE, los fragmenta, calcula embeddings locales y actualiza el índice');

Artisan::command('alerts:match {interest} {--limit=8}', function (): int {
    $results = SemanticSearch::fromEnvironment()->match((string) $this->argument('interest'), (int) $this->option('limit'));

    foreach ($results as $index => $result) {
        $source = $result['source'];
        $this->line(sprintf(
            "\n#%d score=%.3f %s",
            $index + 1,
            $result['score'],
            $source['boe_id'] ?? 'unknown-source',
        ));
        $this->line((string) ($source['title'] ?? 'Untitled'));
        $this->line((string) ($source['url'] ?? ''));
        $this->line($result['justification']);
        $this->line($result['snippet']);
    }

    if ($results === []) {
        $stats = ChunkRepository::fromEnvironment()->stats();

        if ($stats['chunks'] === 0) {
            $this->warn('No hay fragmentos indexados. Ejecuta: php artisan boe:ingest --sample');
        } else {
            $this->warn('No se encontraron alertas suficientemente relevantes para ese interés.');
        }
    }

    return Command::SUCCESS;
})->purpose('Ordena fragmentos indexados del BOE según un interés en lenguaje natural');

Artisan::command('demo:stats', function (): int {
    $stats = ChunkRepository::fromEnvironment()->stats();
    $this->info(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return Command::SUCCESS;
})->purpose('Muestra estadísticas del almacenamiento del índice de la demo');
