# Arquitectura de Sincronización con Presets (Last.fm)

Guía técnica y de referencia arquitectónica para la sincronización semanal de charts de Last.fm basada en presets independientes.

---

## 1. Contexto y Decisión Arquitectónica (El Por Qué de esta Mejora)

### El Problema Anterior (Antipatrón de Acoplamiento Filtro-Entidad)
En el diseño inicial, la tabla `last_fm_charts` contenía columnas de filtrado de negocio:
- `play_count_limit` (entero, e.g. 10, 20)
- `viral_limit` (entero, e.g. 0, 50)

Este diseño introdujo un **antipatrón de mezcla entre la identidad cronológica y los criterios de consulta**:
1. **Duplicación de Semanas:** Si el usuario sincronizaba una semana cronológica para un Top 10 (`play_count_limit=10, viral_limit=0`) y posteriormente decidía consultar un Viral con corte de 25 scrobbles (`play_count_limit=0, viral_limit=25`), el sistema creaba **dos registros independientes en `last_fm_charts`** para exactamente la misma ventana temporal (`user`, `from`, `to`).
2. **Duplicación de Métricas:** En `last_fm_track_playcounts`, las canciones y scrobbles se multiplicaban por cada combinación de límites (`last_fm_chart_id`), desvirtuando la unicidad del scrobble de una semana y saturando la base de datos con datos redundantes.
3. **Imposibilidad de Reutilizar la Semana Pura:** No existía el concepto de "la semana del 1 al 8 de enero de 2026 para svigle", sino "la semana del 1 al 8 con límite 10" vs "la semana del 1 al 8 con viral 20".

### La Solución Arquitectónica: Desacoplamiento por Presets
1. **Semana Cronológica Pura (`last_fm_charts`):**
   - Representa **única y exclusivamente** el periodo temporal (`from`, `to`) para un usuario específico (`user`), con su estado de sincronización (`synced`).
   - El índice único pasó a ser estrictamente `UNIQUE (user, from)`.
   - Independiente de si el usuario quiere ver 5, 10, 50 canciones o solo las que superen cierto umbral de reproducciones.

2. **Presets Especializados (`last_fm_chart_presets`):**
   - Una semana pura puede tener $0..N$ presets calculados y persistidos.
   - Cada preset define su tipo mediante el enum `App\Enums\LastFmChartPresetType`:
     - `Top` (`'top'`): Filtra canciones por su ranking en la semana (`rank <= target_value`).
     - `Viral` (`'viral'`): Filtra canciones por su volumen de reproducciones mínimas (`playcount >= target_value`).
   - `target_value` almacena el valor numérico del corte (e.g. 10 para Top 10, o 30 para Viral 30).
   - Unicidad: `UNIQUE (last_fm_chart_id, type, target_value)`. No se duplica un Top 10 para una misma semana.

3. **Asociación N:M de Canciones por Preset (`last_fm_chart_preset_track`):**
   - La tabla pivote vincula un preset específico con las canciones que calificaron en ese criterio.
   - Guarda los atributos contextuales en el pivot: `playcount` y `rank`.
   - Unicidad: `UNIQUE (last_fm_chart_preset_id, last_fm_track_id)`.

---

## 2. Lo que ya se completó (Subtareas 1 y 2)

### Subtarea 1: Migración y Enum PHP
- **Migración:** `database/migrations/2026_11_08_150000_restructure_charts_and_create_presets_tables.php`.
  - Dropped index `lfm_charts_user_from_limit_viral_limit_unique`.
  - Dropped columns `play_count_limit` y `viral_limit` de `last_fm_charts`.
  - Added unique index `lfm_charts_user_from_unique` (`user`, `from`).
  - Created table `last_fm_chart_presets` con foreign key cascade a `last_fm_charts`.
  - Created table `last_fm_chart_preset_track` con foreign keys cascade a `last_fm_chart_presets` y `last_fm_tracks`.
  - Método `down()` 100% reversible (recrea las columnas y el índice anterior).
- **Enum PHP:** `App\Enums\LastFmChartPresetType`:
  ```php
  enum LastFmChartPresetType: string
  {
      case Top = 'top';
      case Viral = 'viral';
  }
  ```

### Subtarea 2: Modelos, Relaciones y Mantenimiento
- **`App\Models\LastFmChart`:**
  - Limpiado de `$limit` y `$viralLimit`.
  - `#[Fillable(['from', 'to', 'user', 'synced'])]`.
  - `existingCharts(string $user): Collection`: Obtiene todas las semanas puras del usuario indexadas por timestamp `from`.
  - `forChart(string $from, string $to, string $user): static`: Factory semántico puro para crear una semana sin límites.
  - Relación `presets(): HasMany` hacia `LastFmChartPreset`.
- **`App\Models\LastFmChartPreset`:**
  - `#[Fillable(['last_fm_chart_id', 'type', 'target_value'])]`.
  - Casts: `'type' => LastFmChartPresetType::class`, `'target_value' => 'integer'`.
  - Relación `chart(): BelongsTo` hacia `LastFmChart`.
  - Relación `tracks(): BelongsToMany` hacia `LastFmTrack` a través de `last_fm_chart_preset_track` con campos pivot `playcount` y `rank`.
- **`App\Models\LastFmTrack`:**
  - Relación `presets(): BelongsToMany` hacia `LastFmChartPreset` con pivot `playcount` y `rank`.
- **`database/seeders/ResetLastFm.php`:**
  - Actualizado para truncar `last_fm_chart_presets` y `last_fm_chart_preset_track` de manera segura respetando las restricciones de integridad referencial.
- **Batería de Pruebas Unitarias y de Integración (en verde):**
  - `tests/Unit/LastFmChartPresetTypeTest.php`: Cobertura del enum y sus casos (`top`, `viral`).
  - `tests/Feature/LastFmChartPresetModelTest.php`: 4 tests cubriendo creación sin límites viejos, casting de enums, persistencia y lectura en pivote con atributos, y eliminación en cascada (`chart -> presets -> preset_track`).

---

## 3. Modelo de Base de Datos Actual

### Esquema Detallado de Tablas

```
+---------------------------------------------------------------------------------+
|                                 last_fm_charts                                  |
+---------------------------------------------------------------------------------+
| id        : BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY                          |
| from      : VARCHAR(255) / DATETIME                                              |
| to        : VARCHAR(255) / DATETIME                                              |
| user      : VARCHAR(255)                                                        |
| synced    : TINYINT(1) DEFAULT 0                                                |
+---------------------------------------------------------------------------------+
| UNIQUE INDEX: lfm_charts_user_from_unique (user, from)                          |
+---------------------------------------------------------------------------------+
                                       |
                                       | 1:N (Cascade Delete)
                                       v
+---------------------------------------------------------------------------------+
|                              last_fm_chart_presets                              |
+---------------------------------------------------------------------------------+
| id                : BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY                  |
| last_fm_chart_id  : BIGINT UNSIGNED FK -> last_fm_charts.id (onDelete cascade)   |
| type              : ENUM('top', 'viral')                                        |
| target_value      : INT                                                         |
| created_at        : TIMESTAMP                                                   |
| updated_at        : TIMESTAMP                                                   |
+---------------------------------------------------------------------------------+
| UNIQUE INDEX: lfm_chart_presets_chart_type_val_unique                           |
|               (last_fm_chart_id, type, target_value)                            |
+---------------------------------------------------------------------------------+
                                       |
                                       | 1:N (Cascade Delete)
                                       v
+---------------------------------------------------------------------------------+
|                           last_fm_chart_preset_track                            |
+---------------------------------------------------------------------------------+
| id                      : BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY            |
| last_fm_chart_preset_id : BIGINT UNSIGNED FK -> last_fm_chart_presets.id (cascade)
| last_fm_track_id        : BIGINT UNSIGNED FK -> last_fm_tracks.id (cascade)       |
| playcount               : INT                                                   |
| rank                    : INT UNSIGNED NULLABLE                                 |
| created_at              : TIMESTAMP                                             |
| updated_at              : TIMESTAMP                                             |
+---------------------------------------------------------------------------------+
| UNIQUE INDEX: lfm_chart_preset_track_unique                                     |
|               (last_fm_chart_preset_id, last_fm_track_id)                       |
+---------------------------------------------------------------------------------+
                                       ^
                                       | N:1 (Cascade Delete)
+---------------------------------------------------------------------------------+
|                                 last_fm_tracks                                  |
+---------------------------------------------------------------------------------+
| id                : BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY                  |
| last_fm_artist_id : BIGINT UNSIGNED                                             |
| last_fm_album_id  : BIGINT UNSIGNED                                             |
| name              : VARCHAR(255)                                                |
| mbid              : VARCHAR(255) NULLABLE                                       |
| created_at        : TIMESTAMP                                                   |
| updated_at        : TIMESTAMP                                                   |
+---------------------------------------------------------------------------------+
| INDEX: lfm_tracks_artist_album_name_index (last_fm_artist_id, album_id, name)   |
+---------------------------------------------------------------------------------+
```

### Justificación de Índices Únicos
1. **`lfm_charts_user_from_unique` (`user`, `from`)**: Garantiza que una ventana temporal semanal para un usuario solo se inserte una vez en toda la historia de la aplicación.
2. **`lfm_chart_presets_chart_type_val_unique` (`last_fm_chart_id`, `type`, `target_value`)**: Previene registrar dos veces el mismo preset (ejemplo: no pueden coexistir dos registros de `top` con `target_value = 10` para la misma semana).
3. **`lfm_chart_preset_track_unique` (`last_fm_chart_preset_id`, `last_fm_track_id`)**: Garantiza la integridad relacional de la tabla pivote; una pista solo aparece una vez en un preset específico, con su ranking y conteo respectivo.

---

## 4. Guía Táctica para el Desarrollador (Siguiente Sesión)

La siguiente fase de trabajo consiste en implementar **Subtarea 3 (Refactorización de Actions)** y **Subtarea 4 (Actualización del comando Artisan `lastfm:sync-weekly`)**.

---

### Subtarea 3: Actions

#### 1. `App\Actions\LastFm\FetchWeeklyTracksAction`
- **Ubicación:** `app/Actions/LastFm/FetchWeeklyTracksAction.php`.
- **Objetivo:** Consultar la API de Last.fm (`LastFm::getWeeklyTrackList`) y filtrar las pistas según el tipo de preset solicitado antes de enriquecer la información con `getTrackInfo`.
- **Firma del método:**
  ```php
  public static function run(
      LastFmChart $chart,
      LastFmChartPresetType $type,
      int $targetValue
  ): Collection
  ```
- **Lógica de Ejecución:**
  1. Obtener la lista cruda de canciones de la semana desde el cliente HTTP:
     ```php
     $weeklyTrackList = LastFm::getWeeklyTrackList(
         $chart->user,
         $chart->from->timestamp,
         $chart->to->timestamp
     );
     ```
  2. Filtrar la colección según el enum `$type`:
     ```php
     $weeklyTrackList = match ($type) {
         LastFmChartPresetType::Top => $weeklyTrackList->filter(
             fn (array $track): bool => (int) ($track['@attr']['rank'] ?? 0) <= $targetValue
         ),
         LastFmChartPresetType::Viral => $weeklyTrackList->filter(
             fn (array $track): bool => (int) ($track['playcount'] ?? 0) >= $targetValue
         ),
     };
     ```
  3. Mapear cada elemento a su respectivo DTO `WeeklyTrackData`:
     ```php
     return $weeklyTrackList->map(function (array $track): WeeklyTrackData {
         $trackInfo = $this->getTrackInfo($track);

         return WeeklyTrackData::fromApi($track, $trackInfo);
     });
     ```

#### 2. `App\Actions\LastFm\PersistWeeklyChartAction`
- **Ubicación:** `app/Actions/LastFm/PersistWeeklyChartAction.php`.
- **Objetivo:** Persistir el preset de la semana, resolver en cascada artistas, álbumes y canciones (con memoria en caché para evitar N+1), y registrar los tracks calificados en la tabla pivote `last_fm_chart_preset_track`.
- **Firma del método:**
  ```php
  public static function run(
      LastFmChart $chart,
      LastFmChartPresetType $type,
      int $targetValue,
      Collection $weeklyTrackList
  ): LastFmChartPreset
  ```
- **Lógica de Ejecución:**
  1. **Crear o recuperar el Preset:**
     ```php
     $preset = LastFmChartPreset::firstOrCreate([
         'last_fm_chart_id' => $chart->id,
         'type' => $type,
         'target_value' => $targetValue,
     ]);
     ```
  2. **Procesar Tracks y Tags (Anti N+1):**
     - Mantener el patrón existente de `$artistIdMap`, `$albumIdMap`, `$trackIdMap`, `$tagIdMap` y `$trackTagsSyncedMap`.
     - Para cada `WeeklyTrackData`, resolver `$lastFmTrackId = $this->getCachedTrack($track)`.
     - Sincronizar tags asociados en `last_fm_tag_last_fm_track` usando `insertOrIgnore`.
  3. **Preparar datos para el Pivot `last_fm_chart_preset_track`:**
     ```php
     $pivotRows = [];
     $now = now();

     foreach ($weeklyTrackList as $track) {
         $lastFmTrackId = $this->getCachedTrack($track);
         // ... sync tags ...

         $pivotRows[] = [
             'last_fm_chart_preset_id' => $preset->id,
             'last_fm_track_id' => $lastFmTrackId,
             'playcount' => $track->playcount,
             'rank' => $track->rank,
             'created_at' => $now,
             'updated_at' => $now,
         ];
     }
     ```
  4. **Inserción Masiva e Idempotente:**
     ```php
     if ($pivotRows !== []) {
         DB::table('last_fm_chart_preset_track')->upsert(
             $pivotRows,
             ['last_fm_chart_preset_id', 'last_fm_track_id'],
             ['playcount', 'rank', 'updated_at']
         );
     }

     return $preset;
     ```

---

### Subtarea 4: Comando `LastFmSyncWeekly.php`

- **Ubicación:** `app/Console/Commands/LastFmSyncWeekly.php`.
- **Signature:**
  ```php
  #[Signature('lastfm:sync-weekly {user?} {--limit=} {--viral-limit=}')]
  ```

#### Flujo Paso a Paso de Ejecución:

1. **Resolución de Parámetros y Tipo de Preset:**
   ```php
   $user = $this->argument('user') ?? LastFm::getDefaultUser();
   $viralLimit = (int) ($this->option('viral-limit') ?? 0);

   if ($viralLimit > 0) {
       $type = LastFmChartPresetType::Viral;
       $targetValue = $viralLimit;
   } else {
       $type = LastFmChartPresetType::Top;
       $targetValue = (int) ($this->option('limit') ?? config('services.lastfm.top_songs'));
   }
   ```

2. **Carga de Semanas Existentes Puras:**
   - Usar el método limpio sin límites:
     ```php
     $existingCharts = LastFmChart::existingCharts($user);
     ```

3. **Bucle de Sincronización:**
   Para cada periodo `$chart` de `chartsToSync`:
   ```php
   $lastFmChart = $existingCharts->get($chart->from);

   // 1. Si no existe la semana en BD, crearla pura:
   if (! $lastFmChart) {
       $lastFmChart = LastFmChart::forChart(
           (string) $chart->from,
           (string) $chart->to,
           $user
       );
       $existingCharts->put($chart->from, $lastFmChart);
   }

   // 2. Verificar si este preset específico ya fue calculado para la semana:
   $presetExists = $lastFmChart->presets()
       ->where('type', $type)
       ->where('target_value', $targetValue)
       ->exists();

   if ($presetExists) {
       $this->info(sprintf(
           'Preset %s (%d) ya sincronizado para: %s',
           $type->value,
           $targetValue,
           $this->chartPeriod($lastFmChart)
       ));
       continue;
   }

   // 3. Ejecutar Fetch filtrado por preset:
   try {
       $weeklyTrackList = FetchWeeklyTracksAction::run($lastFmChart, $type, $targetValue);
   } catch (Exception $exception) {
       Log::error($exception->getMessage());
       $this->error('Error al sincronizar semana: '.$this->chartPeriod($lastFmChart));
       continue;
   }

   // 4. Si hay canciones, persistir preset y pivotes:
   if ($weeklyTrackList->isNotEmpty()) {
       PersistWeeklyChartAction::run($lastFmChart, $type, $targetValue, $weeklyTrackList);
       $this->renderWeeklyStats($lastFmChart, $weeklyTrackList);
   } else {
       $this->warn('Semana sin canciones para el preset: '.$this->chartPeriod($lastFmChart));
   }

   // 5. Marcar semana sincronizada si no lo estaba:
   if (! $lastFmChart->synced) {
       $lastFmChart->markAsSynced();
   }
   ```

4. **Estadísticas y Telemetría:**
   - Mantener intacta la lógica de `renderLogStats()` y `renderWeeklyStats()` respetando `config('services.lastfm.show_logs')`.

---

## 5. Matriz de Pruebas Necesarias para Subtareas 3 y 4

Al implementar los cambios anteriores, actualizar y pasar los siguientes tests:

1. **`tests/Feature/FetchWeeklyTracksActionTest.php`:**
   - Actualizar llamadas `FetchWeeklyTracksAction::run($chart, LastFmChartPresetType::Top, 10)`.
   - Actualizar llamadas con `LastFmChartPresetType::Viral, 20`.
   - Validar que devuelve DTOs `WeeklyTrackData` filtrados estrictamente por `$rank <= targetValue` o `$playcount >= targetValue`.
2. **`tests/Feature/PersistWeeklyChartActionTest.php`:**
   - Actualizar llamada con argumentos de preset.
   - Verificar que se crea el registro en `last_fm_chart_presets`.
   - Verificar que se insertan los registros en `last_fm_chart_preset_track` con `playcount` y `rank`.
   - Verificar que no se producen duplicados al ejecutar dos veces con los mismos datos (idempotencia).
3. **`tests/Feature/LastFmSyncWeeklyCommandTest.php`:**
   - Probar comando por defecto (`php artisan lastfm:sync-weekly`): crea `LastFmChart` y `LastFmChartPreset` con tipo `Top`.
   - Probar comando con `--viral-limit=50`: crea `LastFmChartPreset` con tipo `Viral` y `target_value=50`.
   - Probar ejecución consecutiva con diferente preset: verifica que **ambos presets coexisten bajo el mismo `last_fm_chart_id`** sin duplicar la semana.
