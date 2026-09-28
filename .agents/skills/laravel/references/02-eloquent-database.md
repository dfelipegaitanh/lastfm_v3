# Referencia Técnica: Eloquent ORM & Base de Datos

Esta guía detalla los estándares de modelado, migraciones seguras y persistencia masiva en Laravel.

---

## 1. Modelos Eloquent con Método `casts()` (Laravel 11+)

> [!IMPORTANT]
> Prohibido declarar la propiedad protegida `$casts = [...]`. Usar exclusivamente el método protegido `casts(): array`.

```php
namespace App\Models;

use App\Enums\ScrobbleSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LastFmTrack extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'last_fm_artist_id',
        'last_fm_album_id',
        'name',
        'mbid',
        'metadata',
        'duration_seconds',
    ];

    /**
     * Tipado y casteo nativo de atributos.
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => AsArrayObject::class,
            'duration_seconds' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(LastFmArtist::class, 'last_fm_artist_id');
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(LastFmAlbum::class, 'last_fm_album_id');
    }

    public function playcounts(): HasMany
    {
        return $this->hasMany(LastFmTrackPlaycounts::class, 'last_fm_track_id');
    }
}
```

---

## 2. Prevención de N+1 con Strict Mode

En `app/Providers/AppServiceProvider.php`:
```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Detiene la ejecución en desarrollo/testing si ocurre Lazy Loading no intencionado
    Model::shouldBeStrict(! app()->isProduction());
}
```

Eager Loading explícito:
```php
$tracks = LastFmTrack::with(['artist', 'album'])->paginate(50);
```

---

## 3. Inserciones Masivas y Upserts Atómicos

Para sincronizaciones masivas de alto volumen, evitar `save()` o `firstOrCreate()` en bucles si el dataset supera miles de filas; utilizar `upsert()`:

```php
use Illuminate\Support\Facades\DB;

LastFmTrackPlaycounts::upsert(
    values: $playcountsArray,
    uniqueBy: ['last_fm_chart_id', 'last_fm_track_id'],
    update: ['playcount', 'rank']
);
```

---

## 4. Migraciones Declarativas y Seguras

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('last_fm_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('last_fm_artist_id')->constrained('last_fm_artists')->cascadeOnDelete();
            $table->foreignId('last_fm_album_id')->nullable()->constrained('last_fm_albums')->nullOnDelete();
            $table->string('name', 255);
            $table->string('mbid', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_fm_artist_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('last_fm_tracks');
    }
};
```
