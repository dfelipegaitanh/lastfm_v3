<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
final class LastFmTag extends Model
{
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(LastFmTrack::class);
    }
}
