<?php

declare(strict_types=1);

namespace App\Actions\LastFm;

use App\Models\LastFmAlbum;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmTag;
use App\Models\LastFmTrack;
use App\Models\LastFmTrackPlaycounts;
use DB;
use Illuminate\Support\Collection;

final class PersistWeeklyChartAction
{
    private static array $albumIdMap = [];

    private static array $artistIdMap = [];

    private static array $tagIdMap = [];

    private static array $trackIdMap = [];

    private static array $trackTagsSyncedMap = [];

    public static function run(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        app(self::class)->execute($lastFmChart, $weeklyTrackList);
    }

    public function execute(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        $playcountsToInsert = [];

        $weeklyTrackList->each(function (array $track) use ($lastFmChart, &$playcountsToInsert): void {

            $lastFmTrackId = $this->getCachedTrack($track);

            if (empty(self::$trackTagsSyncedMap[$lastFmTrackId])) {

                if ($track['tags']) {

                    $tagIds = collect($track['tags'])
                        ->map(fn (array $tag): int => $this->getCachedTagId($tag['name']))
                        ->toArray();

                    // LastFmTrack::find($lastFmTrackId)->tags()->syncWithoutDetaching($tagIds);

                    $now = now();
                    DB::table('last_fm_tag_last_fm_track')->insertOrIgnore(
                        collect($tagIds)->map(fn (int $tagId): array => [
                            'last_fm_tag_id' => $tagId,
                            'last_fm_track_id' => $lastFmTrackId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all()
                    );
                }

                self::$trackTagsSyncedMap[$lastFmTrackId] = true;
            }

            $playcountsToInsert[] = [
                'last_fm_chart_id' => $lastFmChart->id,
                'last_fm_track_id' => $lastFmTrackId,
                'playcount' => $track['playcount'],
                'rank' => $track['rank'],
            ];

        });

        if ($playcountsToInsert !== []) {
            LastFmTrackPlaycounts::upsert(
                $playcountsToInsert,
                ['last_fm_chart_id', 'last_fm_track_id'],
                ['playcount', 'rank']
            );
        }

    }

    private function getCachedAlbumId(int $lastFmArtistId, array $album): int
    {

        $key = $lastFmArtistId.'|'.$album['album'];

        return self::$albumIdMap[$key] ??= LastFmAlbum::firstOrCreate([
            'last_fm_artist_id' => $lastFmArtistId,
            'name' => $album['album'],
            'mbid' => $album['album_mbid'],
        ])->id;
    }

    private function getCachedArtistId(array $track): int
    {
        $key = $track['artist'].'|'.$track['artist_mbid'];

        return self::$artistIdMap[$key] ??= LastFmArtist::firstOrCreate(
            ['name' => $track['artist']],
            ['mbid' => $track['artist_mbid']]
        )->id;
    }

    private function getCachedTagId(string $tagName): int
    {
        return self::$tagIdMap[$tagName] ??= LastFmTag::firstOrCreate(['name' => $tagName])->id;
    }

    private function getCachedTrack(array $track): int
    {

        $lastFmArtistId = $this->getCachedArtistId($track);
        $lastFmAlbumId = $this->getCachedAlbumId($lastFmArtistId, $track);

        $key = $lastFmArtistId.'|'.$lastFmAlbumId.'|'.$track['track'];

        return self::$trackIdMap[$key] ??= LastFmTrack::firstOrCreate([
            'last_fm_artist_id' => $lastFmArtistId,
            'last_fm_album_id' => $lastFmAlbumId,
            'name' => $track['track'],
            'mbid' => $track['track_mbid'],
        ])->id;
    }
}
