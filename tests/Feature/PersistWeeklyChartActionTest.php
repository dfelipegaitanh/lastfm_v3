<?php

declare(strict_types=1);

use App\Actions\LastFm\PersistWeeklyChartAction;
use App\Data\LastFm\WeeklyTrackData;
use App\Models\LastFmAlbum;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmTag;
use App\Models\LastFmTrack;
use App\Models\LastFmTrackPlaycounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    PersistWeeklyChartAction::resetCacheMaps();
});

test('persists weekly tracks and relations correctly from WeeklyTrackData DTOs', function (): void {
    $chart = LastFmChart::create([
        'from' => '1735689600',
        'to' => '1736294400',
        'user' => 'svigle',
        'play_count_limit' => 10,
    ]);

    $tracks = collect([
        new WeeklyTrackData(
            artist: 'Radiohead',
            artistMbid: 'mbid-art-1',
            track: 'Karma Police',
            trackMbid: 'mbid-trk-1',
            album: 'OK Computer',
            albumMbid: 'mbid-alb-1',
            playcount: 30,
            rank: 1,
            tags: ['alternative', 'rock'],
        ),
        new WeeklyTrackData(
            artist: 'Radiohead',
            artistMbid: 'mbid-art-1',
            track: 'Paranoid Android',
            trackMbid: 'mbid-trk-2',
            album: 'OK Computer',
            albumMbid: 'mbid-alb-1',
            playcount: 25,
            rank: 2,
            tags: ['rock', 'art rock'],
        ),
    ]);

    PersistWeeklyChartAction::run($chart, $tracks);

    expect(LastFmArtist::count())->toBe(1)
        ->and(LastFmAlbum::count())->toBe(1)
        ->and(LastFmTrack::count())->toBe(2)
        ->and(LastFmTrackPlaycounts::count())->toBe(2)
        ->and(LastFmTag::count())->toBe(3);

    $artist = LastFmArtist::first();
    expect($artist->name)->toBe('Radiohead')
        ->and($artist->mbid)->toBe('mbid-art-1');

    $album = LastFmAlbum::first();
    expect($album->name)->toBe('OK Computer')
        ->and($album->last_fm_artist_id)->toBe($artist->id);

    $playcounts = LastFmTrackPlaycounts::where('last_fm_chart_id', $chart->id)->get();
    expect($playcounts->pluck('rank')->sort()->values()->all())->toBe([1, 2]);

    $pivotCount = DB::table('last_fm_tag_last_fm_track')->count();
    expect($pivotCount)->toBe(4); // 'alternative' & 'rock' for track 1 + 'rock' & 'art rock' for track 2

    // Probar idempotencia: una segunda ejecución no debe duplicar registros
    PersistWeeklyChartAction::run($chart, $tracks);

    expect(LastFmArtist::count())->toBe(1)
        ->and(LastFmAlbum::count())->toBe(1)
        ->and(LastFmTrack::count())->toBe(2)
        ->and(LastFmTrackPlaycounts::count())->toBe(2)
        ->and(DB::table('last_fm_tag_last_fm_track')->count())->toBe(4);
});
