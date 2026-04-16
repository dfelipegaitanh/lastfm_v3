<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
#[Fillable(['from', 'to', 'user', 'synced', 'play_count_limit'])]
class LastFmChart extends Model
{
    protected function casts(): array
    {
        return [
            'from' => 'datetime:Y-m-d H:i:s',
            'to' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public static function forChart(string $from, string $to, string $user): static
    {
        return static::firstOrCreate([
            'from' => $from,
            'to' => $to,
            'user' => $user,
            'play_count_limit' => config('services.lastfm.top_songs'),
        ]);
    }

    public function markAsSynced(): void
    {
        $this->synced = true;
        $this->save();
    }
}
