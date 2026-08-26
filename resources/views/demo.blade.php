<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buscador Semántico BOE</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #0f172a; color: white; padding: 2rem; }
        main { max-width: 1100px; margin: 0 auto; padding: 2rem; }
        section { background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
        label, .field-label { display: block; font-weight: 700; margin-bottom: .4rem; }
        input[type="text"], input[type="date"] { width: 100%; padding: .75rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 1rem; }
        button { background: #2563eb; border: 0; border-radius: 10px; color: white; cursor: pointer; font-weight: 700; padding: .75rem 1rem; }
        button:disabled { cursor: wait; opacity: .75; }
        button.secondary { background: #475569; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        .checkbox-field { align-items: center; border: 1px solid #cbd5e1; border-radius: 10px; display: flex; min-height: 47px; padding: 0 .75rem; }
        .checkbox-field input { margin: 0 .5rem 0 0; }
        .stat { display: inline-block; background: #e0f2fe; border-radius: 999px; margin-right: .5rem; padding: .35rem .7rem; }
        .message { background: #dcfce7; border-color: #86efac; }
        .loading-message { align-items: center; background: #eff6ff; border: 1px solid #93c5fd; border-radius: 10px; color: #1e3a8a; display: none; gap: .6rem; margin-top: 1rem; padding: .75rem; }
        .loading-message.is-visible { display: flex; }
        .spinner { animation: spin 1s linear infinite; border: 3px solid #bfdbfe; border-top-color: #2563eb; border-radius: 999px; display: inline-block; height: 1.1rem; width: 1.1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .error { color: #b91c1c; font-size: .9rem; margin-top: .4rem; }
        .result { border-top: 1px solid #e2e8f0; padding: 1rem 0; }
        .score { color: #0369a1; font-weight: 800; }
        .source { color: #475569; font-size: .9rem; }
        blockquote { border-left: 4px solid #93c5fd; margin: .75rem 0; padding-left: .9rem; color: #334155; }
        mark { background: #fef08a; border-radius: 4px; padding: 0 .15rem; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
<header>
    <h1>Buscador Semántico BOE</h1>
    <p>Explora publicaciones del BOE mediante ingesta, embeddings locales y búsqueda semántica con fuentes citadas.</p>
</header>
<main>
    @if (session('message') || $message)
        <section class="message">{{ session('message') ?? $message }}</section>
    @endif

    <section>
        <h2>Estado del índice</h2>
        <span class="stat">Almacenamiento: {{ $stats['store'] }}</span>
        <span class="stat">Documentos: {{ $stats['documents'] }}</span>
        <span class="stat">Fragmentos: {{ $stats['chunks'] }}</span>
        <span class="stat">Fechas: {{ $stats['date_from'] && $stats['date_to'] ? \Illuminate\Support\Carbon::parse($stats['date_from'])->format('d/m/Y') . ' - ' . \Illuminate\Support\Carbon::parse($stats['date_to'])->format('d/m/Y') : 'sin datos' }}</span>
    </section>

    <section>
        <h2>1. Obtener datos del BOE</h2>
        <form id="ingest-form" method="post" action="{{ route('demo.ingest') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="from">Fecha inicial</label>
                    <input id="from" name="from" type="date" value="2024-06-01">
                    @error('from')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="to">Fecha final</label>
                    <input id="to" name="to" type="date" value="2024-06-01">
                    @error('to')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <span class="field-label">Origen de datos</span>
                    <label class="checkbox-field"><input type="checkbox" name="sample" value="1"> Usar muestra incluida de 2024</label>
                </div>
                <div>
                    <span class="field-label">Modo de índice</span>
                    <label class="checkbox-field"><input type="checkbox" name="replace" value="1"> Reemplazar índice actual</label>
                </div>
            </div>
            <p><button id="ingest-button" type="submit">Construir índice semántico</button></p>
            <div id="ingest-loading" class="loading-message" role="status" aria-live="polite">
                <span class="spinner" aria-hidden="true"></span>
                <span>Construyendo el índice semántico. La descarga, extracción y generación de embeddings puede tardar unos minutos.</span>
            </div>
        </form>
        <p class="source">Este proyecto no es un servicio oficial del BOE ni está afiliado a él. El rango máximo es de 31 días. La muestra incluida es reproducible, no requiere red e ignora las fechas seleccionadas. Por defecto, la ingesta añade o actualiza documentos sin duplicarlos; marca “Reemplazar índice actual” para empezar de cero.</p>
    </section>

    <section>
        <h2>2. Buscar alertas por interés en lenguaje natural</h2>
        <form method="get" action="{{ route('demo.alerts') }}">
            <label for="interest">Interés</label>
            <input id="interest" name="interest" type="text" value="{{ old('interest', $interest ?: 'productos químicos peligrosos y obligaciones de información') }}">
            <p><button class="secondary" type="submit">Buscar alertas semánticas</button></p>
        </form>
        @error('interest')<p class="error">{{ $message }}</p>@enderror
    </section>

    @if ($results)
        <section>
            <h2>Alertas priorizadas</h2>
            @foreach ($results as $result)
                <article class="result">
                    <div class="score">Puntuación {{ number_format($result['score'], 3) }}</div>
                    <h3>{{ $result['source']['boe_id'] ?? 'Fuente desconocida' }} · {{ $result['source']['title'] ?? 'Documento sin título' }}</h3>
                    @php
                        $sourceDate = $result['source']['date'] ?? null;
                        $formattedDate = $sourceDate ? \Illuminate\Support\Carbon::parse($sourceDate)->format('d/m/Y') : 'n/d';
                    @endphp
                    <p class="source">
                        Fecha: {{ $formattedDate }} ·
                        <a href="{{ $result['source']['url'] ?? '#' }}" target="_blank" rel="noopener">fuente</a>
                    </p>
                    <p>{{ $result['justification'] }}</p>
                    @php
                        $highlightedSnippet = e($result['snippet']);
                        foreach ($result['matched_terms'] ?? [] as $term) {
                            $highlightedSnippet = preg_replace(
                                '/(' . preg_quote(e($term), '/') . ')/iu',
                                '<mark>$1</mark>',
                                $highlightedSnippet,
                            );
                        }
                    @endphp
                    <blockquote>{!! $highlightedSnippet !!}</blockquote>
                </article>
            @endforeach
        </section>
    @elseif ($interest)
        <section>
            <h2>Sin resultados</h2>
            <p>No hay fragmentos indexados o no se encontraron alertas suficientemente relevantes para el interés indicado. Prueba primero a construir el índice semántico con la muestra incluida o utiliza una consulta más específica.</p>
        </section>
    @endif
</main>
<script>
    document.getElementById('ingest-form')?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        const button = document.getElementById('ingest-button');
        const loading = document.getElementById('ingest-loading');

        if (form.dataset.submitted === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitted = 'true';
        button.disabled = true;
        button.textContent = 'Construyendo índice...';
        loading.classList.add('is-visible');
    });
</script>
</body>
</html>
