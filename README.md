# Buscador Semántico BOE

Buscador Semántico BOE indexa publicaciones disponibles públicamente en el BOE y permite localizar fragmentos relevantes mediante consultas en lenguaje natural. Es un proyecto independiente: **no es un servicio oficial del BOE ni está afiliado, respaldado o avalado por el BOE**.

## Inicio rápido

1. Creá un archivo local e ignorado `.env` con la configuración de tu entorno. Generá una clave de aplicación con `php artisan key:generate` después de instalar dependencias o dentro del contenedor.
2. Construí y levantá los servicios:

   ```bash
   docker compose build
   docker compose up -d
   ```

3. Cargá el fixture sintético sin usar red:

   ```bash
   docker compose exec app php artisan boe:ingest --sample --replace
   ```

4. Abrí `http://localhost:8000` y buscá un tema. El servicio de embeddings expone `http://localhost:8001/health`.

## Alcance y funciones

- Ingesta de páginas públicas del BOE por fecha o rango de hasta 31 días.
- Extracción de contenido, fragmentación con solapamiento y embeddings locales.
- Búsqueda semántica con coincidencias literales, puntuación, fragmento y URL de origen.
- Persistencia en MongoDB cuando está disponible y fallback a JSON.
- Fixture sintético para validar el flujo sin red ni reutilizar texto público.

No sustituye la consulta de fuentes oficiales, el asesoramiento jurídico ni una política de archivo documental.

## Arquitectura

```text
Laravel / Blade / Artisan
  └─ App\Services\Boe
       ├─ BoeFetcher y HtmlContentExtractor
       ├─ TextChunker y EmbeddingClient
       └─ ChunkRepository y SemanticSearch

FastAPI + sentence-transformers ── embeddings locales
MongoDB o JSON                 ── índice de fragmentos
```

La aplicación descarga o carga documentos, extrae el contenido, genera embeddings y guarda fragmentos con metadatos de fuente. La interfaz y el comando `alerts:match` muestran resultados trazables.

## Configuración y seguridad

Mantené `.env` fuera del control de versiones y proporcioná `APP_KEY` desde ese archivo o desde el entorno de despliegue. No hay una clave de desarrollo en Docker Compose.

Las variables de proyecto son:

| Variable | Propósito |
| --- | --- |
| `BOE_DATA_STORE` | Selecciona `json` o `mongodb`. |
| `BOE_JSON_INDEX_PATH` | Ruta del índice JSON local. |
| `BOE_FIXTURE_PATH` | Ruta del fixture sintético. |
| `BOE_MONGODB_URI`, `BOE_MONGODB_DATABASE`, `BOE_MONGODB_COLLECTION` | Conexión y destino de MongoDB. |
| `BOE_EMBEDDING_SERVICE_URL` | URL del servicio de embeddings. |
| `BOE_EMBEDDING_ALLOW_HASH_FALLBACK` | Habilita el fallback determinista para desarrollo. |

Revisá límites de red, retención de caché y acceso a MongoDB antes de desplegar. La caché de documentos descargados se almacena en `storage/app/boe/boe-documents/`.

## Tests y validación

Con las dependencias instaladas, ejecutá:

```bash
php artisan test
./vendor/bin/pint --test
php scripts/validate_core.php
```

En Docker, anteponé `docker compose exec app` a los comandos PHP. Para comprobar las vistas, usá `php artisan view:cache` y luego `php artisan view:clear`.

## Fuentes y atribución

Las ingestas reales apuntan a páginas públicas de `boe.es`; cada resultado conserva su URL de origen para facilitar la verificación. Consultá las condiciones de uso, avisos legales y requisitos de atribución de la fuente antes de redistribuir contenido descargado. El fixture incluido es totalmente sintético y no contiene texto reutilizado de publicaciones del BOE.

## Licencia

Este proyecto se distribuye bajo la [licencia MIT](LICENSE).
