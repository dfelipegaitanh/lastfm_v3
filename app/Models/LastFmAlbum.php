<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['last_fm_artist_id', 'name', 'mbid'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmAlbum extends Model
{
    public function artist(): BelongsTo
    {
        return $this->belongsTo(LastFmArtist::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(LastFmTrack::class);
    }
}
