<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

#[WithoutTimestamps]
#[Fillable(['from', 'to', 'user', 'synced'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmChart extends Model
{
    public static function existingCharts(string $user): Collection
    {
        return self::where('user', $user)
            ->get()
            ->keyBy(fn (LastFmChart $c) => $c->from->timestamp);
    }

    public static function forChart(string $from, string $to, string $user): static
    {
        return self::create([
            'from' => $from,
            'to' => $to,
            'user' => $user,
        ]);
    }

    public function markAsSynced(): void
    {
        $this->synced = true;
        $this->save();
    }

    public function presets(): HasMany
    {
        return $this->hasMany(LastFmChartPreset::class);
    }

    public function trackPlaycounts(): HasMany
    {
        return $this->hasMany(LastFmTrackPlaycounts::class);
    }

    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(LastFmTrack::class, LastFmTrackPlaycounts::class);
    }

    protected function casts(): array
    {
        return [
            'from' => 'datetime:Y-m-d H:i:s',
            'to' => 'datetime:Y-m-d H:i:s',
            'synced' => 'boolean',
        ];
    }
}
