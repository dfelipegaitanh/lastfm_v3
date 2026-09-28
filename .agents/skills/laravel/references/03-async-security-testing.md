# Referencia Técnica: Asincronía, Seguridad & Testing

Esta guía detalla el procesamiento de colas resiliente, el scheduler moderno de tareas y la suite de pruebas en **Pest PHP**.

---

## 1. Jobs Resilientes y Deduplicación

```php
namespace App\Jobs;

use App\Models\User;
use App\Services\LastFm\LastFmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWeeklyChartJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 180;
    public int $uniqueFor = 3600;

    public function __construct(
        public User $user,
        public int $fromTimestamp,
        public int $toTimestamp
    ) {}

    public function uniqueId(): string
    {
        return "sync_{$this->user->id}_{$this->fromTimestamp}";
    }

    public function backoff(): array
    {
        return [15, 60, 180]; // Retroceso exponencial ante fallos de API
    }

    public function handle(LastFmClient $client): void
    {
        // Procesar semana
    }
}
```

---

## 2. Programación de Tareas (`routes/console.php`)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('lastfm:sync-weekly')
    ->dailyAt('03:00')
    ->withoutOverlapping(expiresAt: 120)
    ->runInBackground();
```

---

## 3. Pruebas Automatizadas con Pest PHP

### Pruebas de Integración con Fakes de Red
Nunca realizar llamadas HTTP reales a Last.fm dentro de la suite de pruebas:

```php
use App\Console\Commands\LastFmSyncWeekly;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\{artisan, assertDatabaseHas};

it('syncs weekly charts and caches track info without hitting live api', function () {
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response([
            'weeklytrackchart' => [
                'track' => [
                    [
                        'name' => 'Paranoid Android',
                        'artist' => ['#text' => 'Radiohead', 'mbid' => 'abc-123'],
                        'playcount' => '42',
                        '@attr' => ['rank' => '1'],
                    ],
                ],
            ],
            'track' => [
                'album' => ['title' => 'OK Computer', 'mbid' => 'alb-456'],
                'toptags' => ['tag' => [['name' => 'rock']]],
            ],
        ], 200),
    ]);

    $user = User::factory()->create(['lastfm_username' => 'testuser']);

    artisan('lastfm:sync-weekly testuser')
        ->assertSuccessful();

    assertDatabaseHas('last_fm_tracks', [
        'name' => 'Paranoid Android',
    ]);
});
```
