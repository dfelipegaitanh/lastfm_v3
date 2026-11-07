<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'mbid', 'image_url'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmArtist extends Model
{
    public function albums(): HasMany
    {
        return $this->hasMany(LastFmAlbum::class);
    }

    public function playcounts(): HasManyThrough
    {
        return $this->hasManyThrough(LastFmTrackPlaycounts::class, LastFmTrack::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(LastFmTrack::class);
    }
}
