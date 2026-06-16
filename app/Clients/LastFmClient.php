<?php

declare(strict_types=1);

namespace App\Clients;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class LastFmClient
{
    private string $apiKey;

    private string $baseUrl;

    private static array $callLog = [];

    private ?string $defaultUser;

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

    public function getAlbumInfo(string $artist, string $album): Collection
    {
        return $this->resolve(
            'album.getinfo',
            ['artist' => $artist, 'album' => $album],
            'album'
        );
    }

    public function getDefaultUser(): ?string
    {
        return $this->defaultUser;
    }

    public function getTrackInfo(string $artist, string $track): Collection
    {
        return $this->resolve(
            'track.getinfo',
            ['artist' => $artist, 'track' => $track],
            'track'
        );
    }

    public function getUserInfo(string $user): Collection
    {

        return $this->resolve('user.getinfo', ['user' => $user], 'user');

    }

    public function getWeeklyChartList(string $user): Collection
    {

        return $this->resolve(method: 'user.getweeklychartlist', params: ['user' => $user], dataKey: 'weeklychartlist.chart', cache: false);
    }

    public function getWeeklyTrackList(string $user, int $from, int $to): Collection
    {

        return $this->resolve(
            'user.getweeklytrackchart',
            ['user' => $user, 'from' => $from, 'to' => $to],
            'weeklytrackchart.track'
        );
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)->withQueryParameters([
            'api_key' => $this->apiKey,
            'format' => 'json',
        ]);
    }

    /**
     * @throws Exception
     */
    private function getRequest(string $method, array $params, ?string $dataKey): array
    {
        self::$callLog[] = [
            'method' => $method,
            'params' => $params,
        ];

        $response = $this->client()->get('', array_merge([
            'method' => $method,
        ], $params));

        if (! $response->successful()) {
            throw new Exception('Error al obtener datos de Last.fm Method: '.$method.' Params: '.json_encode($params).' Response: '.$response->body());
        }

        $data = $dataKey ? $response->json($dataKey) : $response->json();

        return is_array($data) ? $data : [];
    }

    private function resolve(string $method, array $params, ?string $dataKey, ?string $customKey = null, bool $cache = true): Collection
    {
        $cacheKey = $customKey ?? 'lfm.'.md5($method.serialize($params));

        if ($cache === false) {
            Cache::forget($cacheKey);
        }

        return Collection::make(Cache::rememberForever($cacheKey, function () use ($method, $params, $dataKey): array {
            return $this->getRequest($method, $params, $dataKey);
        }));
    }
}
