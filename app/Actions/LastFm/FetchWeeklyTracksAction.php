<?php

declare(strict_types=1);

namespace App\Actions\LastFm;

use App\Data\LastFm\WeeklyTrackData;
use App\Facades\LastFm;
use App\Models\LastFmChart;
use Illuminate\Support\Collection;

final class FetchWeeklyTracksAction
{
    public static function run(LastFmChart $chart): Collection
    {
        return app(self::class)->execute($chart);
    }

    /**
     * @return Collection<int, WeeklyTrackData>
     */
    public function execute(LastFmChart $chart): Collection
    {

        $weeklyTrackList = LastFm::getWeeklyTrackList($chart->user, $chart->from->timestamp, $chart->to->timestamp);

        $weeklyTrackList = ($chart->viral_limit)
            ? $weeklyTrackList->filter(fn (array $track): bool => (int) ($track['playcount'] ?? 0) >= $chart->viral_limit)
            : $weeklyTrackList->filter(fn (array $track): bool => (int) ($track['@attr']['rank'] ?? 0) <= $chart->play_count_limit);

        return $weeklyTrackList
            ->map(function (array $track): WeeklyTrackData {

                $trackInfo = $this->getTrackInfo($track);

                return WeeklyTrackData::fromApi($track, $trackInfo);
            });
    }

    private function getArtist(array $track): string
    {
        return (string) (data_get($track, 'artist.#text') ?? data_get($track, 'artist.name') ?? data_get($track, 'artist') ?? '');
    }

    private function getTrackInfo(array $track): ?Collection
    {

        $trackName = $this->getTrackName($track);
        $artist = $this->getArtist($track);

        if ($artist === '' || $trackName === '') {
            return null;
        }

        return LastFm::getTrackInfo($artist, $trackName);
    }

    private function getTrackName(array $track): string
    {
        return (string) (data_get($track, 'name') ?? data_get($track, 'track') ?? '');
    }
}
