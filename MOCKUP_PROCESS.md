# Last.fm Statistics Table Mockup — Engineering Process & Architectural Decisions

This document details the end-to-end engineering process executed to design, implement, and verify the Last.fm weekly statistics table mockup in `lastfm_v3` (Laravel 13 + Inertia v3 + React 19 + TypeScript + Tailwind CSS v4 + shadcn/ui). It outlines what each specialized agent (`orquestador`, `backend-developer`, `frontend-developer`, and `qa-engineer`) delivered and the architectural and domain rationale behind every technical decision.

---

## 1. Executive Overview & Goal

### What Was Requested
A simple, zero-authentication, single-route (`GET /`) React statistics table mockup fed directly from a Laravel controller using hardcoded data that accurately mirrors the application's real `LastFm*` Eloquent domain models.

### Why This Approach Was Chosen
- **End-to-End Pipeline Validation Without External Coupling**: Feeding the page from an Inertia controller with typed PHP DTOs validates the full stack—Laravel routing, Wayfinder route generation, PHP DTO serialization, Inertia v3 page props, and React 19 + TypeScript rendering—without depending on local MySQL state or live Last.fm API calls.
- **Zero-Friction Swap to Production Queries**: By shaping the hardcoded payload after the real relational schema (`LastFmChart`, `LastFmChartPreset`, `LastFmTrack`, `LastFmArtist`, `LastFmAlbum`, `LastFmTag`, and `LastFmTrackPlaycounts`), the frontend component tree and TypeScript interfaces are already production-ready. Replacing the hardcoded dataset with a live Eloquent query later requires zero frontend changes.

---

## 2. Multi-Agent Architecture & Orchestration (`orquestador`)

### Why the Orchestrator Does Not Write Code Directly
In `lastfm_v3`, the `orquestador` acts exclusively as the Tech Lead and full-stack architect:
- **Separation of Concerns**: Restricting `backend-developer` to PHP (`app/`, `routes/`, `tests/`), `frontend-developer` to client-side code (`resources/js/`, `resources/css/`), and `qa-engineer` to read-only verification eliminates accidental cross-layer breakage.
- **Contract-First Engineering**: A common failure mode in full-stack Inertia applications is schema drift between what a Laravel controller returns and what a React page expects. By defining an immutable data contract before a single line of code is written, both backend and frontend agents build against the exact same specification.

### How Domain Rules (`.agents/rules/lastfm-domain.md`) Guided the Plan
Before planning, the Orchestrator inspected `.agents/rules/lastfm-domain.md` and the existing codebase:
1. **Respecting the 7+1 Domain Models**: The domain is composed of `LastFmArtist`, `LastFmAlbum`, `LastFmTrack`, `LastFmTag`, `LastFmChart`, `LastFmChartPreset`, and `LastFmTrackPlaycounts` (plus the `LastFmChartPresetType` enum with `'top'` and `'viral'` cases). The mockup data contract was designed to represent a flattened projection of this exact entity graph.
2. **Chart Identity (`user` and `play_count_limit`)**: In `lastfm_v3`, a weekly chart's identity is uniquely defined by `(user, from, play_count_limit, viral_limit)` and its associated preset (`LastFmChartPresetType`). Therefore, the contract explicitly includes `user`, `preset_type`, `preset_name`, `play_count_limit`, and `viral_limit` in the summary metadata.
3. **Configuration Discipline**: Direct `env()` calls outside `config/*.php` are strictly forbidden because they return `null` once `php artisan config:cache` is active. The controller reads fallback defaults exclusively through `config('services.lastfm.default_user')`.

### The Immutable Data Contract
The Orchestrator defined two structures shared identically across PHP and TypeScript:
- **`StatChartSummaryData` (PHP) ↔ `StatChartSummary` (TypeScript)**: Captures `LastFmChart` + `LastFmChartPreset` metadata and precomputed KPIs (`chart_id`, `user`, `preset_type`, `preset_name`, `from`, `to`, `play_count_limit`, `viral_limit`, `total_tracks`, `total_playcount`, `unique_artists`, `unique_albums`).
- **`StatTrackRowData` (PHP) ↔ `StatTrackRow` (TypeScript)**: Captures a ranked row from `LastFmTrackPlaycounts` joined with `LastFmTrack`, `LastFmArtist`, `LastFmAlbum`, and `LastFmTag`.

---

## 3. Step 1: Backend Implementation (`backend-developer`)

### What Was Built
- **`app/Data/LastFm/StatChartSummaryData.php`**: A `final readonly` DTO extending `App\Data\Data` that encapsulates weekly chart metadata and summary counters.
- **`app/Data/LastFm/StatTrackRowData.php`**: A `final readonly` DTO extending `App\Data\Data` that flattens the relational graph into a single row (`id`, `rank`, `playcount`, `track_name`, `track_mbid`, `last_fm_artist_id`, `artist_name`, `artist_mbid`, `artist_image_url`, `last_fm_album_id`, `album_name`, `album_mbid`, `album_image_url`, `tags`).
- **`app/Http/Controllers/WelcomeController.php`**: A thin invokable controller (`__invoke(): Response`) that constructs the `StatChartSummaryData` instance and 8 `StatTrackRowData` rows, returning `Inertia::render('welcome', [...])`.
- **`routes/web.php`**: Registered the single public route `Route::get('/', WelcomeController::class)->name('home')` and regenerated typed frontend route helpers via `php artisan wayfinder:generate`.
- **`tests/Feature/WelcomeControllerTest.php`**: A Pest feature test suite (1 test, 75 assertions) validating the `HTTP 200` response, the `welcome` Inertia component, all summary metrics, and every property of the first track row.

### Why These Decisions Were Made
- **Why DTOs Instead of Raw Associative Arrays**: Passing loose associative arrays between backend layers and Inertia views obscures property types and invites silent key mismatches. Extending `App\Data\Data` (`Arrayable`, `JsonSerializable`) with `final readonly` constructor-promoted properties enforces strict PHP 8.4 typing while serializing cleanly into Inertia JSON props.
- **Why Mix Local Assets and `null` Values in Hardcoded Rows**: In the real database schema, `last_fm_artists.image_url`, `last_fm_albums.image_url`, `last_fm_albums` foreign keys, and `mbid` columns are all nullable (`nullOnDelete()` or missing MusicBrainz IDs from Last.fm). By deliberately mixing valid local assets (`/assets/images/logo_header.png`), `null` image URLs, `null` album relations (e.g., standalone singles), and `null` MBIDs across the 8 hardcoded tracks, the backend forces the UI to prove it handles real-world edge cases gracefully.
- **Why an Invokable Controller on `GET /`**: Matches the requirement for a single, zero-auth entry point with minimal ceremony while keeping `routes/web.php` clean and declarative.

---

## 4. Step 2: Frontend Implementation (`frontend-developer`)

### What Was Built
- **`resources/js/types/lastfm.ts` & `resources/js/types/index.ts`**: Strict TypeScript interfaces (`LastFmChartPresetType`, `StatChartSummary`, `StatTrackRow`, and `WelcomePageProps`) mirroring the PHP DTOs 1:1.
- **`resources/js/pages/welcome.tsx`**:
  - Replaced legacy static placeholder constants with live Inertia page props (`{ chartSummary, tracks }: WelcomePageProps`).
  - Built a header displaying the Last.fm username (`@{chartSummary.user}`), chart date range (`from` → `to`), preset badge (`Top` vs `Viral`), and active `play_count_limit` / `viral_limit` thresholds.
  - Built a 4-card KPI strip displaying **Total Scrobbles**, **Ranked Tracks**, **Unique Artists**, and **Unique Albums**.
  - Added an interactive client-side search input and clickable genre tag filter pills derived dynamically from the dataset.
  - Built a responsive statistics table displaying rank badges (with top-3 visual emphasis), album artwork avatars with automatic 2-letter initials fallback, track name + optional MusicBrainz `MBID` indicator, artist avatar + name, album cell with graceful `"Single / Sin álbum"` fallback when `album_name` is `null`, tag badges, and a scrobble count column with a proportional progress bar.
- **Pre-existing TypeScript Fixes & Bundle Compilation**: Resolved strict TypeScript issues in `resources/js/app.tsx`, `resources/js/lib/utils.ts`, and `resources/js/types/ui.ts`, and built the production bundle via `npm run build`.

### Why These Decisions Were Made
- **Why Reuse `shadcn/ui` Primitives (`Card`, `Badge`, `Avatar`, `Input`, `Button`)**: Reusing existing primitives avoids bespoke CSS maintenance and guarantees consistent light/dark mode theming through Tailwind CSS v4 semantic tokens (`bg-background`, `bg-card`, `text-foreground`, `text-muted-foreground`, `border-border`).
- **Why Semantic Table Markup & Accessibility (`modern-web-guidance`)**:
  - Wrapped the table in a keyboard-focusable horizontal scroll container (`tabIndex={0}`, `role="region"`, `aria-labelledby="weekly-chart-table-title"`) so keyboard and mobile users can scroll wide statistical columns safely.
  - Included a screen-reader `<caption>` (`sr-only`), explicit `<th scope="col">` column headers, and `<th scope="row">` on each track's primary identifying cell.
  - Applied `tabular-nums` (`font-variant-numeric: tabular-nums`) to rank, scrobble counts, and share percentages so digits align vertically for fast visual scanning, paired with a relative progress bar scaled against the #1 track's playcount.
- **Why Client-Side Search and Tag Filtering**: Provides immediate interactivity to inspect and filter the mockup dataset by track, artist, album, or Last.fm tag without introducing extra backend endpoints or query parameter round-trips.

---

## 5. Step 3: Quality Assurance & Verification (`qa-engineer`)

### What Was Executed (Read-Only)
1. **Git Scope Audit (`git status --short`, `git diff --stat`)**: Verified that `backend-developer` and `frontend-developer` modified only their authorized directories and respected all domain boundaries.
2. **Automated Test Execution (`php artisan test`)**: Confirmed `Tests\Feature\WelcomeControllerTest` passed (`1 passed, 75 assertions`). Also audited the broader test suite and flagged 2 pre-existing failures in `LastFmSyncWeeklyCommandTest` as unrelated technical debt.
3. **TypeScript Static Analysis (`npx tsc --noEmit --ignoreDeprecations 6.0`)**: Confirmed zero type errors across the entire `resources/js/` codebase.
4. **Route Verification (`php artisan route:list --except-vendor`)**: Verified `GET|HEAD /` resolves to `WelcomeController` under the route name `home` with no auth middleware attached.
5. **1:1 Data Contract & Rule Compliance Audit**: Verified every field name, type, and nullability modifier across `StatChartSummaryData.php`, `StatTrackRowData.php`, `WelcomeController.php`, `resources/js/types/lastfm.ts`, and `resources/js/pages/welcome.tsx`, plus adherence to `config('services.lastfm.default_user')` over `env()`.

### Why QA Runs as a Separate Read-Only Agent
Separating verification from implementation prevents "grading one's own homework." Because `qa-engineer` is strictly prohibited from editing files, it cannot silently weaken a failing assertion or mask a TypeScript error—it must report the exact diagnostic output back to the Orchestrator so fixes are routed to the responsible domain specialist.

---

## 6. How to Replace the Hardcoded Mockup with Live Database Queries Next

Because the DTOs and React page already adhere to the domain schema, transitioning `WelcomeController` from hardcoded data to live MySQL queries requires **zero changes to `resources/js/pages/welcome.tsx` or `resources/js/types/lastfm.ts`**:

1. **Create a Read Action (`app/Actions/LastFm/GetWeeklyChartStatsAction.php`)**:
   - Query the latest completed `LastFmChart` for `config('services.lastfm.default_user')` along with its `LastFmChartPreset`.
   - Query `LastFmTrackPlaycounts` filtered by `(last_fm_chart_id, last_fm_chart_preset_id)` ordered by `playcount DESC`.
   - Eager-load `track.artist`, `track.album`, and `track.tags` (`with(['track.artist', 'track.album', 'track.tags'])`) to avoid N+1 queries.
2. **Map Eloquent Models to Existing DTOs**:
   - Map the `LastFmChart` + `LastFmChartPreset` aggregates into `StatChartSummaryData`.
   - Map each `LastFmTrackPlaycounts` record into `StatTrackRowData`.
3. **Inject the Action into `WelcomeController::__invoke()`**:
   - Replace the hardcoded array construction in `WelcomeController` with `$action->handle()`, keeping the exact `Inertia::render('welcome', ['chartSummary' => ..., 'tracks' => ...])` return signature intact.
