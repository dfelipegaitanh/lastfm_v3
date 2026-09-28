# Referencia Técnica: Arquitectura, Service Container & Capa HTTP

Esta guía condensa los patrones de arquitectura y la capa HTTP para Laravel 11+ / 12 / 13 con PHP 8.2+.

---

## 1. Request Lifecycle & `bootstrap/app.php`

En Laravel 11+, no existen `app/Http/Kernel.php` ni `app/Console/Kernel.php`. La configuración se define de forma fluida en `bootstrap/app.php`:

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            // Telemetría o registro personalizado
        });
    })->create();
```

---

## 2. Inyección de Dependencias en `AppServiceProvider`

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LastFm\LastFmClient;
use App\Contracts\MusicProviderInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton resuelto una sola vez por ciclo de vida
        $this->app->singleton(LastFmClient::class, function ($app) {
            return new LastFmClient(
                apiKey: config('services.lastfm.api_key'),
                apiSecret: config('services.lastfm.api_secret'),
                timeout: 10
            );
        });

        // Enlace de interfaz a implementación
        $this->app->bind(MusicProviderInterface::class, LastFmClient::class);
    }
}
```

---

## 3. Controladores y FormRequests Desacoplados

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'artist_id' => ['required', 'integer', Rule::exists('last_fm_artists', 'id')],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
        ];
    }
}
```

Controlador delgado respondiendo a Inertia o JSON:

```php
namespace App\Http\Controllers;

use App\Http\Requests\StoreAlbumRequest;
use App\Models\LastFmAlbum;
use Inertia\Inertia;
use Inertia\Response;

class AlbumController extends Controller
{
    public function store(StoreAlbumRequest $request): Response
    {
        $album = LastFmAlbum::create($request->validated());

        return Inertia::render('Albums/Show', [
            'album' => $album->load('artist'),
        ]);
    }
}
```
