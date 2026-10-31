<?php

declare(strict_types=1);

namespace App\Actions\LastFm;

use App\Facades\LastFm;
use App\Models\LastFmChart;
use Illuminate\Support\Collection;

final class FetchWeeklyTracksAction
{
    public static function run(string $user, int $limit, LastFmChart $chart): Collection
    {
        return app(self::class)->execute($user, $limit, $chart);
    }

    public function execute(string $user, int $limit, LastFmChart $chart): Collection
    {
        return LastFm::getWeeklyTrackList($user, $chart->from->timestamp, $chart->to->timestamp)
            ->filter(fn ($track): bool => ($track['@attr']['rank'] ?? 0) <= $limit)
            ->map(function (array $track): array {
                $artist = $track['artist']['#text'] ?? '';
                $trackName = $track['name'] ?? '';
                $albumTitle = 'Unknown Album';
                $albumMbid = '';
                $tags = [];

                if ($artist && $trackName) {
                    $trackInfo = LastFm::getTrackInfo($artist, $trackName);
                    $tags = (array) data_get($trackInfo, 'toptags.tag', []);
                    $albumTitle = data_get($trackInfo->get('album'), 'title', 'Unknown Album');
                    $albumMbid = data_get($trackInfo->get('album'), 'mbid');
                }

                return [
                    'artist' => $artist,
                    'artist_mbid' => $track['artist']['mbid'] ?? '',
                    'track' => $trackName,
                    'track_mbid' => $track['mbid'] ?? '',
                    'playcount' => (int) ($track['playcount'] ?? 0),
                    'rank' => (int) ($track['@attr']['rank'] ?? 0),
                    'album' => $albumTitle,
                    'album_mbid' => $albumMbid,
                    'tags' => $tags,
                ];
            });
    }
}
