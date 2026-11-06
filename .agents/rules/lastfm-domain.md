---
trigger: model_decision
description: Fuente de verdad del dominio Last.fm de lastfm_v3 (esquema de BD, índices, pipeline lastfm:sync-weekly, caché de la API, seeder de reset, variables LASTFM_* del .env y comandos prohibidos). Consultar antes de tocar app/, database/, routes/, config/ o de planificar cualquier feature que lea o escriba datos de Last.fm.
---

# Dominio Last.fm — Fuente de verdad

Este documento describe cómo funciona HOY el dominio. Si el código cambia, actualiza este archivo en el mismo cambio.

## 1. Configuración (`.env` → `config/services.php` → código)

| Variable `.env` | Clave de config | Default en config | Uso |
| :--- | :--- | :--- | :--- |
| `LASTFM_API_KEY` | `services.lastfm.key` | — | `LastFmClient` la inyecta como `api_key` en cada request. |
| `LASTFM_USER` | `services.lastfm.user` | `svigle` | `LastFm::getDefaultUser()`; usuario por defecto de `lastfm:sync-weekly`. |
| `LASTFM_API_URL` | `services.lastfm.url` | `http://ws.audioscrobbler.com/2.0/` | Base URL del cliente HTTP. |
| `LASTFM_TOP_SONGS` | `services.lastfm.top_songs` | `20` | `$limit` del sync → se persiste como `last_fm_charts.play_count_limit`. |
| `LASTFM_SHOW_LOGS` | `services.lastfm.show_logs` | `false` | Activa `activateQueryLog()` (`DB::listen`) y `renderLogStats()` en los comandos de sync. |

Reglas:
- Leer SIEMPRE con `config('services.lastfm.*')`. Prohibido `env()` fuera de `config/`.
- Prohibido imprimir, loguear, copiar a tests o commitear el valor de `LASTFM_API_KEY`.
- `LASTFM_TOP_SONGS` vale `10` en el `.env` local, pero el default de config es `20`. Si la variable desaparece del `.env`, el sync cambia silenciosamente a 20 y crea un set de semanas NUEVO (ver índice único abajo).

## 2. Esquema de base de datos (MySQL local `lastfm_v3`)

Siete tablas del dominio, todas con prefijo `last_fm_` y modelo `App\Models\LastFm*`:

| Tabla | Modelo | Columnas | Índices |
| :--- | :--- | :--- | :--- |
| `last_fm_charts` | `LastFmChart` | `id`, `from` (string), `to` (string), `user` (string), `synced` (bool, default false), `play_count_limit` (int) | **UNIQUE `lfm_charts_user_from_limit_unique` (`user`, `from`, `play_count_limit`)** |
| `last_fm_artists` | `LastFmArtist` | `id`, `name`, `mbid` (nullable), timestamps | UNIQUE `lfm_artists_name_unique` (`name`) |
| `last_fm_albums` | `LastFmAlbum` | `id`, `last_fm_artist_id`, `name`, `mbid` (nullable), timestamps | INDEX `lfm_albums_artist_name_index` (`last_fm_artist_id`, `name`) |
| `last_fm_tracks` | `LastFmTrack` | `id`, `last_fm_artist_id`, `last_fm_album_id`, `name`, `mbid` (nullable), timestamps | INDEX `lfm_tracks_artist_album_name_index` (`last_fm_artist_id`, `last_fm_album_id`, `name`) |
| `last_fm_track_playcounts` | `LastFmTrackPlaycounts` | `id`, `last_fm_track_id`, `last_fm_chart_id`, `playcount` (int), `rank` (unsigned, nullable), timestamps | UNIQUE `lfm_playcounts_chart_track_unique` (`last_fm_chart_id`, `last_fm_track_id`) |
| `last_fm_tags` | `LastFmTag` | `id`, `name`, timestamps | UNIQUE `lfm_tags_name_unique` (`name`) |
| `last_fm_tag_last_fm_track` | pivot | `id`, `last_fm_tag_id`, `last_fm_track_id`, timestamps | UNIQUE `lfm_tag_track_unique` (`last_fm_tag_id`, `last_fm_track_id`); FKs con `cascadeOnDelete` |

Claves para no romper nada:
- **`play_count_limit` es parte de la identidad de una semana.** La misma semana sincronizada con límite 10 y con límite 20 son DOS registros distintos de `last_fm_charts`, con sus propios playcounts. Toda consulta de charts DEBE filtrar por `user` + `play_count_limit` (como hace `LastFmChart::existingCharts()`), o mezclará rankings de límites distintos.
- `from` / `to` son columnas VARCHAR. La API entrega unix timestamps, pero el cast `datetime` del modelo los guarda como `Y-m-d H:i:s` (zona horaria de `config('app.timezone')`). Compara siempre vía `$chart->from->timestamp`, nunca comparando strings.
- Solo el pivot tiene FK reales. `foreignIdFor()` en charts/tracks/albums/playcounts crea la columna SIN constraint: la integridad referencial depende del código.
- `last_fm_charts` no tiene timestamps (`#[WithoutTimestamps]`).
- Tablas del framework: `users` (con columnas de 2FA), `cache`, `jobs`. No pertenecen al dominio.

Relaciones Eloquent:
- `LastFmArtist` hasMany `albums`, `tracks`.
- `LastFmAlbum` belongsTo `artist`; hasMany `tracks`.
- `LastFmTrack` belongsTo `artist`, `album`; hasMany `playcounts`; belongsToMany `tags`; scope `byArtistName()`.
- `LastFmChart` hasMany `trackPlaycounts`; hasManyThrough `tracks`.
- `LastFmTrackPlaycounts` → `chart`, `track` (definidas como hasOne; ver deuda técnica).
- `LastFmTag` belongsToMany `tracks`.

Convenciones de modelos: `final class`, `declare(strict_types=1);`, PHP Attributes `#[Fillable]`, `#[Hidden]`, `#[Scope]`, `#[WithoutTimestamps]`. Prohibido crear modelos o tablas paralelas genéricas (`artists`, `tracks`, `charts`…).

## 3. Anatomía de Comandos de Sincronización (`app/Console/Commands/`)

`LastFmSyncWeekly` (`php artisan lastfm:sync-weekly {user?} {--limit=}`) es el primer comando de sincronización, pero vendrán más comandos (tanto para Last.fm como para otras APIs externas) que compartirán su estructura base:

1. **Resolución de parámetros**: argumentos/opciones del comando con fallback a `config('services.<servicio>.*')`.
2. **Telemetría condicional (`show_logs`)**: solo si `config('services.lastfm.show_logs')` (`LASTFM_SHOW_LOGS`) es `true`, se invoca `activateQueryLog()` para enganchar `DB::listen` y acumular conteos en `$this->dbQueries` (separando `CACHE` del resto), y al final de `renderWeeklyStats()` se ejecuta `renderLogStats()` con las tablas de llamadas HTTP (`<Servicio>Client::getCallLog()` / `getCallCount()`) y consultas SQL. Si es `false` (default), solo se imprime la tabla de datos sincronizados sin sobrecargar memoria ni consola.
3. **Orquestación por Actions**: el comando solo coordina el bucle y el manejo de errores (`try/catch` + `Log::error` sin abortar todo el lote); delega la lectura externa en una Action de *Fetch* (`FetchWeeklyTracksAction`) y la escritura en BD en una Action de *Persist* (`PersistWeeklyChartAction`).
4. **Persistencia idempotente y sin N+1**: uso de mapas en memoria (`$artistIdMap`, `$albumIdMap`, `$trackIdMap`, `$tagIdMap`) con `firstOrCreate`, `insertOrIgnore` para pivots y `upsert` para métricas (`last_fm_track_playcounts`), marcando el lote como `synced = true` solo al completar.

En el caso específico de `lastfm:sync-weekly`, el sync es idempotente por (`user`, `from`, `play_count_limit`) y filtra semanas desde `registered.unixtime`.

## 4. Clientes HTTP (`app/Clients/`) y caché de APIs

- Cada integración externa vive en su propio cliente en `app/Clients/<Servicio>Client.php` con su Facade en `app/Facades/` y configuración en `config/services.php` (la base de datos sigue siendo exclusivamente del dominio Last.fm).
- **Cliente actual (`App\Clients\LastFmClient`, Facade `App\Facades\LastFm`)**:
  - HTTP: `connectTimeout(5)`, `timeout(8)`, `retry(2, 200)`, `format=json`.
  - Caché: `Cache::rememberForever('lfm.'.md5($method.serialize($params)))` sobre `CACHE_STORE=file`. Todas las respuestas se cachean para siempre, EXCEPTO `user.getweeklychartlist`.
  - Consecuencia: después de un reset de BD, re-sincronizar es casi instantáneo y casi no consume API, porque `track.getinfo` y `getweeklytrackchart` salen de caché. `php artisan cache:clear` borra esa caché y obliga a re-pedir TODO a Last.fm (miles de requests).
- Todo consumo HTTP externo va encapsulado en su cliente dentro de `app/Clients/` (usando un método `resolve()` con caché y `callLog`), nunca con `Http::` suelto en Actions o Controllers.

## 5. Reset de datos (`database/seeders/ResetLastFm.php`)

- `php artisan db:seed` → `DatabaseSeeder` → `ResetLastFm`.
- `ResetLastFm` hace `TRUNCATE` de las 7 tablas del dominio dentro de `Schema::withoutForeignKeyConstraints()`. No toca `users` ni la caché.
- **Regla de mantenimiento**: `ResetLastFm.php` solo se modifica cuando una migración crea o elimina una tabla del dominio (para incluirla o quitarla del `truncate`).
- Flujo de reset completo: `php artisan db:seed` → `php artisan lastfm:sync-weekly [user] [--limit=N]`.

## 6. Comandos prohibidos sin aprobación explícita del usuario

Ningún agente ejecuta estos comandos por iniciativa propia; el Orquestador debe pedir aprobación al usuario y pasarla por escrito en la delegación:

- `php artisan db:seed` (cualquier clase), `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe` → destruyen los datos sincronizados.
- `php artisan migrate` contra la BD local → solo tras `php artisan migrate --pretend` revisado y aprobado.
- `php artisan lastfm:sync-weekly` → consume la API real y escribe en la BD.
- `php artisan cache:clear` / `cache:forget lfm.*` → invalida la caché de Last.fm.
- Cualquier lectura o volcado del `.env`.

## 7. Testing

- `phpunit.xml` usa SQLite `:memory:`, `CACHE_STORE=array` y `QUEUE_CONNECTION=sync`: `php artisan test` NO toca la BD MySQL ni la caché real.
- Los tests deben usar `Http::fake()` para `ws.audioscrobbler.com`. Prohibido pegarle a la API real desde tests.
- Los mapas estáticos de `PersistWeeklyChartAction` y `LastFmClient::$callLog` sobreviven entre tests del mismo proceso: llama `LastFmClient::resetCallLog()` y ten en cuenta que los mapas de IDs pueden apuntar a filas que ya no existen tras `RefreshDatabase`.
- Hoy solo existen `tests/Unit/ExampleTest.php` y `tests/Feature/ExampleTest.php`: no hay cobertura real del dominio.

## 8. Convenciones de arquitectura backend

- Controllers delgados: reciben request validado (Form Request), llaman una Action/Service y devuelven `Inertia::render()`.
- Actions: `app/Actions/<Dominio>/<Verbo><Sustantivo>Action.php`, `final class`, `declare(strict_types=1);`, `public static function run(...)` que delega en `app(self::class)->execute(...)`.
- DTOs: `app/Data/<Dominio>/<Nombre>Data.php`, `final readonly class` con constructores tipados y named constructors (`fromApi()`, `fromModel()`). Son el contrato entre API externa, Actions y props de Inertia.
- Services: `app/Services/` solo para orquestar varias Actions o lógica de dominio reutilizable.
- Clients: `app/Clients/` para HTTP externo.

## 9. Deuda técnica conocida (NO corregir sin pedido explícito)

- `LastFmServiceProvider` registra el singleton para `App\Services\LastFmClient`, clase que NO existe (la real es `App\Clients\LastFmClient`). El binding no aplica y la Facade resuelve una instancia nueva por auto-resolución.
- `LastFmTrackPlaycounts::chart()` / `track()` usan `hasOne` donde semánticamente corresponde `belongsTo`; su `#[Fillable]` no incluye `last_fm_chart_id` (el `upsert` lo salta).
- `FetchWeeklyTracksAction` devuelve arrays crudos, no DTOs, y hace 1 llamada `track.getinfo` por canción (mitigado por la caché).
- `app/Actions/Commands/` está vacío.
- `resources/js/pages/welcome.tsx` usa datos mock hardcodeados (`ARTISTS`), no props del backend.

