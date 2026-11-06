<?php

declare(strict_types=1);

namespace App\Actions\LastFm;

use App\Data\LastFm\WeeklyTrackData;
use App\Facades\LastFm;
use App\Models\LastFmChart;
use Illuminate\Support\Collection;

final class FetchWeeklyTracksAction
{
    /**
     * @return Collection<int, WeeklyTrackData>
     */
    public static function run(string $user, int $limit, LastFmChart $chart): Collection
    {
        return app(self::class)->execute($user, $limit, $chart);
    }

    /**
     * @return Collection<int, WeeklyTrackData>
     */
    public function execute(string $user, int $limit, LastFmChart $chart): Collection
    {
        return LastFm::getWeeklyTrackList($user, $chart->from->timestamp, $chart->to->timestamp)
            ->filter(fn (array $track): bool => (int) ($track['@attr']['rank'] ?? 0) <= $limit)
            ->map(function (array $track): WeeklyTrackData {
                $artist = (string) (data_get($track, 'artist.#text') ?? data_get($track, 'artist.name') ?? data_get($track, 'artist') ?? '');
                $trackName = (string) (data_get($track, 'name') ?? data_get($track, 'track') ?? '');
                $trackInfo = null;

                if ($artist !== '' && $trackName !== '') {
                    $trackInfo = LastFm::getTrackInfo($artist, $trackName);
                }

                return WeeklyTrackData::fromApi($track, $trackInfo);
            });
    }
}
