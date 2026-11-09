<?php

declare(strict_types=1);

use App\Enums\LastFmChartPresetType;
use App\Models\LastFmAlbum;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmChartPreset;
use App\Models\LastFmTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create a chart without play count limit or viral limit columns', function (): void {
    $chart = LastFmChart::forChart('2026-01-01 00:00:00', '2026-01-08 00:00:00', 'svigle');

    expect($chart)->toBeInstanceOf(LastFmChart::class)
        ->and($chart->user)->toBe('svigle')
        ->and($chart->from->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
        ->and($chart->to->format('Y-m-d H:i:s'))->toBe('2026-01-08 00:00:00')
        ->and($chart->refresh()->synced)->toBeFalse();

    $this->assertDatabaseHas('last_fm_charts', [
        'id' => $chart->id,
        'user' => 'svigle',
    ]);
});

test('can create a preset with enum type and target value cast', function (): void {
    $chart = LastFmChart::forChart('2026-01-01 00:00:00', '2026-01-08 00:00:00', 'svigle');

    $preset = LastFmChartPreset::create([
        'last_fm_chart_id' => $chart->id,
        'type' => LastFmChartPresetType::Top,
        'target_value' => 10,
    ]);

    expect($preset->type)->toBe(LastFmChartPresetType::Top)
        ->and($preset->target_value)->toBe(10)
        ->and($preset->chart->id)->toBe($chart->id);

    $this->assertDatabaseHas('last_fm_chart_presets', [
        'id' => $preset->id,
        'last_fm_chart_id' => $chart->id,
        'type' => 'top',
        'target_value' => 10,
    ]);
});

test('can associate tracks to preset and retrieve pivot playcount and rank', function (): void {
    $chart = LastFmChart::forChart('2026-01-01 00:00:00', '2026-01-08 00:00:00', 'svigle');
    $preset = $chart->presets()->create([
        'type' => LastFmChartPresetType::Top,
        'target_value' => 10,
    ]);

    $artist = LastFmArtist::create(['name' => 'Radiohead']);
    $album = LastFmAlbum::create([
        'name' => 'OK Computer',
        'last_fm_artist_id' => $artist->id,
    ]);
    $track = LastFmTrack::create([
        'name' => 'Karma Police',
        'last_fm_artist_id' => $artist->id,
        'last_fm_album_id' => $album->id,
    ]);

    $preset->tracks()->attach($track->id, ['playcount' => 45, 'rank' => 1]);

    $loadedPreset = $chart->presets()->first();
    expect($loadedPreset)->not->toBeNull()
        ->and($loadedPreset->tracks)->toHaveCount(1);

    $loadedTrack = $loadedPreset->tracks->first();
    expect($loadedTrack->id)->toBe($track->id)
        ->and($loadedTrack->pivot->playcount)->toBe(45)
        ->and($loadedTrack->pivot->rank)->toBe(1);

    $trackPreset = $track->presets->first();
    expect($trackPreset->id)->toBe($preset->id)
        ->and($trackPreset->pivot->playcount)->toBe(45)
        ->and($trackPreset->pivot->rank)->toBe(1);
});

test('chart deletion cascades to presets and pivot records', function (): void {
    $chart = LastFmChart::forChart('2026-01-01 00:00:00', '2026-01-08 00:00:00', 'svigle');
    $preset = $chart->presets()->create([
        'type' => LastFmChartPresetType::Top,
        'target_value' => 10,
    ]);

    $artist = LastFmArtist::create(['name' => 'Radiohead']);
    $album = LastFmAlbum::create([
        'name' => 'OK Computer',
        'last_fm_artist_id' => $artist->id,
    ]);
    $track = LastFmTrack::create([
        'name' => 'Karma Police',
        'last_fm_artist_id' => $artist->id,
        'last_fm_album_id' => $album->id,
    ]);

    $preset->tracks()->attach($track->id, ['playcount' => 45, 'rank' => 1]);

    $this->assertDatabaseHas('last_fm_charts', ['id' => $chart->id]);
    $this->assertDatabaseHas('last_fm_chart_presets', ['id' => $preset->id]);
    $this->assertDatabaseHas('last_fm_chart_preset_track', [
        'last_fm_chart_preset_id' => $preset->id,
        'last_fm_track_id' => $track->id,
    ]);

    $chart->delete();

    $this->assertDatabaseMissing('last_fm_charts', ['id' => $chart->id]);
    $this->assertDatabaseMissing('last_fm_chart_presets', ['id' => $preset->id]);
    $this->assertDatabaseMissing('last_fm_chart_preset_track', [
        'last_fm_chart_preset_id' => $preset->id,
        'last_fm_track_id' => $track->id,
    ]);
});
