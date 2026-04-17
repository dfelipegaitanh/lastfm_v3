<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'mbid'])]
class LastFmTrack extends Model
{
    public function artist(): BelongsTo
    {
        return $this->belongsTo(LastFmArtist::class);
    }

    public function playcounts(): HasMany
    {
        return $this->hasMany(LastFmTrackPlaycounts::class);
    }

    #[Scope]
    public function byArtistName(string $artistName): void
    {
        $this->whereHas('artist', function ($query) use ($artistName) {
            $query->where('name', $artistName);
        });
    }
}
