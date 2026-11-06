<?php

declare(strict_types=1);

namespace App\Actions\LastFm;

use App\Data\LastFm\WeeklyTrackData;
use App\Models\LastFmAlbum;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmTag;
use App\Models\LastFmTrack;
use App\Models\LastFmTrackPlaycounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PersistWeeklyChartAction
{
    private static array $albumIdMap = [];

    private static array $artistIdMap = [];

    private static array $tagIdMap = [];

    private static array $trackIdMap = [];

    private static array $trackTagsSyncedMap = [];

    public static function resetCacheMaps(): void
    {
        self::$albumIdMap = [];
        self::$artistIdMap = [];
        self::$tagIdMap = [];
        self::$trackIdMap = [];
        self::$trackTagsSyncedMap = [];
    }

    /**
     * @param  Collection<int, WeeklyTrackData>  $weeklyTrackList
     */
    public static function run(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        app(self::class)->execute($lastFmChart, $weeklyTrackList);
    }

    /**
     * @param  Collection<int, WeeklyTrackData>  $weeklyTrackList
     */
    public function execute(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        $playcountsToInsert = [];

        $weeklyTrackList->each(function (WeeklyTrackData $track) use ($lastFmChart, &$playcountsToInsert): void {
            $lastFmTrackId = $this->getCachedTrack($track);

            if (empty(self::$trackTagsSyncedMap[$lastFmTrackId])) {
                if ($track->tags !== []) {
                    $tagIds = collect($track->tags)
                        ->map(fn (string $tagName): int => $this->getCachedTagId($tagName))
                        ->all();

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
                'playcount' => $track->playcount,
                'rank' => $track->rank,
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

    private function getCachedAlbumId(int $lastFmArtistId, WeeklyTrackData $track): int
    {
        $key = $lastFmArtistId.'|'.$track->album;

        return self::$albumIdMap[$key] ??= LastFmAlbum::firstOrCreate([
            'last_fm_artist_id' => $lastFmArtistId,
            'name' => $track->album,
            'mbid' => $track->albumMbid,
        ])->id;
    }

    private function getCachedArtistId(WeeklyTrackData $track): int
    {
        $key = $track->artist.'|'.($track->artistMbid ?? '');

        return self::$artistIdMap[$key] ??= LastFmArtist::firstOrCreate(
            ['name' => $track->artist],
            ['mbid' => $track->artistMbid]
        )->id;
    }

    private function getCachedTagId(string $tagName): int
    {
        return self::$tagIdMap[$tagName] ??= LastFmTag::firstOrCreate(['name' => $tagName])->id;
    }

    private function getCachedTrack(WeeklyTrackData $track): int
    {
        $lastFmArtistId = $this->getCachedArtistId($track);
        $lastFmAlbumId = $this->getCachedAlbumId($lastFmArtistId, $track);

        $key = $lastFmArtistId.'|'.$lastFmAlbumId.'|'.$track->track;

        return self::$trackIdMap[$key] ??= LastFmTrack::firstOrCreate([
            'last_fm_artist_id' => $lastFmArtistId,
            'last_fm_album_id' => $lastFmAlbumId,
            'name' => $track->track,
            'mbid' => $track->trackMbid,
        ])->id;
    }
}
