<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['playcount', 'last_fm_track_id', 'rank'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmTrackPlaycounts extends Model
{
    public function chart(): HasOne
    {
        return $this->hasOne(LastFmChart::class, 'id', 'last_fm_chart_id');
    }

    public function track(): HasOne
    {
        return $this->hasOne(LastFmTrack::class, 'id', 'last_fm_track_id');
    }
}
