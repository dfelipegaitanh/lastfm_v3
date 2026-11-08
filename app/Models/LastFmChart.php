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
#[Fillable(['from', 'to', 'user', 'synced', 'play_count_limit', 'viral_limit'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmChart extends Model
{
    protected $attributes = [
        'viral_limit' => 0,
    ];

    public static function existingCharts(string $user, int $limit, int $viralLimit = 0): Collection
    {
        return self::where('user', $user)
            ->where('play_count_limit', $limit)
            ->where('viral_limit', $viralLimit)
            ->get()
            ->keyBy(fn (LastFmChart $c) => $c->from->timestamp);
    }

    public static function forChart(string $from, string $to, int $limit, string $user, int $viralLimit = 0): static
    {
        return self::create([
            'from' => $from,
            'to' => $to,
            'user' => $user,
            'play_count_limit' => $limit,
            'viral_limit' => $viralLimit,
        ]);
    }

    public function markAsSynced(): void
    {
        $this->synced = true;
        $this->save();
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
            'play_count_limit' => 'integer',
            'viral_limit' => 'integer',
        ];
    }
}
