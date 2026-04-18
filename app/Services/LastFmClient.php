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

    protected static array $runtimeCache = [];

    protected static array $callLog = [];

    public function __construct()
    {
        $this->baseUrl = config('services.lastfm.url', 'http://ws.audioscrobbler.com/2.0/');
        $this->apiKey = config('services.lastfm.key');
        $this->defaultUser = config('services.lastfm.user');
    }

    public static function getCallCount(): int
    {
        return count(self::$callLog);
    }

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

    private function resolve(string $method, array $params, ?string $dataKey, ?string $customKey = null): Collection
    {
        $cacheKey = $customKey ?? 'lfm.'.md5($method.serialize($params));

        if (isset(self::$runtimeCache[$cacheKey])) {
            return self::$runtimeCache[$cacheKey];
        }

        $data = Collection::make(Cache::rememberForever($cacheKey, function () use ($method, $params, $dataKey) {
            return $this->getRequest($method, $params, $dataKey);
        }));

        self::$runtimeCache[$cacheKey] = $data;

        return $data;
    }

    public function getWeeklyChartList(string $user): Collection
    {

        return $this->resolve('user.getweeklychartlist', ['user' => $user], 'weeklychartlist.chart');
    }

    public function getUserInfo(string $user): Collection
    {

        return $this->resolve('user.getinfo', ['user' => $user], 'user');

    }

    public function getWeeklyTrackList(string $user, int $from, int $to): Collection
    {

        return $this->resolve(
            'user.getweeklytrackchart',
            ['user' => $user, 'from' => $from, 'to' => $to],
            'weeklytrackchart.track'
        );
    }

    public function getAlbumInfo(string $artist, string $album): Collection
    {
        return $this->resolve(
            'album.getinfo',
            ['artist' => $artist, 'album' => $album],
            'album'
        );
    }

    public function getTrackInfo(string $artist, string $track): Collection
    {
        // TODO: Aquí unificas el acceso a track.album de una vez
        return $this->resolve(
            'track.getinfo',
            ['artist' => $artist, 'track' => $track],
            'track'
        );
    }
}
