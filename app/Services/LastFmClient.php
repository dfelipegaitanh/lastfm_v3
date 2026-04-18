<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LastFmClient
{
    protected string $baseUrl;

    protected string $apiKey;

    protected ?string $defaultUser;

    protected static array $callLog = [];

    public function __construct()
    {
        $this->baseUrl = config('services.lastfm.url', 'http://ws.audioscrobbler.com/2.0/');
        $this->apiKey = config('services.lastfm.key');
        $this->defaultUser = config('services.lastfm.user');
    }

    /** Total real HTTP calls made (cache hits excluded). */
    public static function getCallCount(): int
    {
        return count(self::$callLog);
    }

    /** Full log including cache hits. */
    public static function getCallLog(): Collection
    {
        return Collection::make(self::$callLog);
    }

    public static function resetCallLog(): void
    {
        self::$callLog = [];
    }

    private function getRequest(string $method, array $params, ?string $dataKey): array
    {
        self::$callLog[] = [
            'method' => $method,
            'params' => $params,
            'called_at' => now()->toDateTimeString(),
        ];

        $response = $this->client()->get('', array_merge([
            'method' => $method,
        ], $params));

        if (! $response->successful()) {
            return [];
        }

        $data = $dataKey ? $response->json($dataKey) : $response->json();

        return is_array($data) ? $data : [];
    }

    public function getDefaultUser(): ?string
    {
        return $this->defaultUser;
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)->withQueryParameters([
            'api_key' => $this->apiKey,
            'format' => 'json',
        ]);
    }

    private function cachedRequest(string $method, string $key, array $params, ?string $dataKey): Collection
    {
        $data = Cache::rememberForever(
            $key,
            fn () => $this->getRequest(
                method: $method,
                params: $params,
                dataKey: $dataKey
            ));

        return Collection::make($data);
    }

    public function getWeeklyChartList(string $user): Collection
    {

        return $this->cachedRequest(
            'user.getweeklychartlist',
            'user.weeklychartlist.'.$user,
            ['user' => $user],
            'weeklychartlist.chart'
        );
    }

    public function getUserInfo(string $user): Collection
    {

        return $this->cachedRequest(
            'user.getinfo',
            'user.info.'.$user,
            ['user' => $user],
            'user'
        );

    }

    public function getWeeklyTrackList(string $user, int $from, int $to): Collection
    {

        return $this->cachedRequest(
            'user.getweeklytrackchart',
            'user.weeklytrackchart.'.$user.'.'.$from.'.'.$to,
            [
                'user' => $user,
                'from' => $from,
                'to' => $to,
            ],
            dataKey: 'weeklytrackchart.track'
        );
    }

    public function getAlbumInfo(string $artist, string $album): Collection
    {
        return $this->cachedRequest(
            'album.getinfo',
            'album.info.'.md5($artist.'|'.$album),
            [
                'artist' => $artist,
                'album' => $album,
            ],
            'album'
        );
    }

    public function getTrackInfo(string $artist, string $track): Collection
    {
        return $this->cachedRequest(
            'track.getinfo',
            'track.info.'.md5($artist.'|'.$track),
            [
                'artist' => $artist,
                'track' => $track,
            ],
            'track.album'
        );
    }
}
