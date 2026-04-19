<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'mbid', 'last_fm_artist_id'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmTrack extends Model
{
    public function artist(): BelongsTo
    {
        return $this->belongsTo(LastFmArtist::class);
    }

    #[Scope]
    public function byArtistName(string $artistName): void
    {
        $this->whereHas('artist', function ($query) use ($artistName): void {
            $query->where('name', $artistName);
        });
    }

    public function playcounts(): HasMany
    {
        return $this->hasMany(LastFmTrackPlaycounts::class);
    }
}
