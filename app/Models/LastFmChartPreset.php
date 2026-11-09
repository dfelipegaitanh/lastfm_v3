<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LastFmChartPresetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['last_fm_chart_id', 'type', 'target_value'])]
#[Hidden(['created_at', 'updated_at'])]
final class LastFmChartPreset extends Model
{
    public function chart(): BelongsTo
    {
        return $this->belongsTo(LastFmChart::class, 'last_fm_chart_id');
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(
            LastFmTrack::class,
            'last_fm_chart_preset_track',
            'last_fm_chart_preset_id',
            'last_fm_track_id'
        )->withPivot(['playcount', 'rank'])->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'type' => LastFmChartPresetType::class,
            'target_value' => 'integer',
        ];
    }
}
