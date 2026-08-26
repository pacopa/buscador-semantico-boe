<?php

namespace App\Http\Controllers;

use App\Services\Boe\BoeIndexer;
use App\Services\Boe\ChunkRepository;
use App\Services\Boe\SemanticSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class DemoController extends Controller
{
    public function index(): View
    {
        return view('demo', [
            'stats' => ChunkRepository::fromEnvironment()->stats(),
            'results' => [],
            'interest' => '',
            'message' => null,
        ]);
    }

    public function ingest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'sample' => ['nullable', 'boolean'],
            'replace' => ['nullable', 'boolean'],
        ]);

        try {
            $stats = BoeIndexer::fromEnvironment()->ingestRange(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                (bool) ($validated['sample'] ?? false),
                5,
                (bool) ($validated['replace'] ?? false),
            );
        } catch (Throwable $exception) {
            return redirect('/')->with('message', 'No se pudo actualizar el índice con ese rango de fechas. Prueba otro rango del BOE o activa la muestra incluida. Detalle: ' . $exception->getMessage());
        }

        $skippedMessage = $stats['skipped_days'] > 0
            ? sprintf(' Días omitidos sin documentos disponibles: %d.', $stats['skipped_days'])
            : '';

        return redirect('/')->with('message', sprintf(
            'Índice actualizado: %d fragmentos añadidos/actualizados de %d documentos procesados. Total actual: %d fragmentos de %d documentos en almacenamiento %s.%s',
            $stats['ingested_chunks'],
            $stats['ingested_documents'],
            $stats['chunks'],
            $stats['documents'],
            $stats['store'],
            $skippedMessage,
        ));
    }

    public function alerts(Request $request): View
    {
        if (!$request->filled('interest')) {
            return $this->index();
        }

        $validated = $request->validate([
            'interest' => ['required', 'string', 'min:3'],
        ]);

        $interest = $validated['interest'];

        return view('demo', [
            'stats' => ChunkRepository::fromEnvironment()->stats(),
            'results' => SemanticSearch::fromEnvironment()->match($interest, 8),
            'interest' => $interest,
            'message' => null,
        ]);
    }
}
