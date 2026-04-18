<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'mbid'])]
final class LastFmArtist extends Model
{
    public function tracks(): HasMany
    {
        return $this->hasMany(LastFmTrack::class);
    }
}
