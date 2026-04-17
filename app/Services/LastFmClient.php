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

    public function __construct()
    {
        $this->baseUrl = config('services.lastfm.url', 'http://ws.audioscrobbler.com/2.0/');
        $this->apiKey = config('services.lastfm.key');
        $this->defaultUser = config('services.lastfm.user');
    }

    private function getRequest(string $method, array $params, ?string $dataKey): array
    {
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

    public function getWeeklyChartList(string $user): Collection
    {
        $data = Cache::rememberForever(
            'user.weeklychartlist.'.$user,
            fn () => $this->getRequest(
            method: 'user.getweeklychartlist',
            params: ['user' => $user],
            dataKey: 'weeklychartlist.chart'
        ));

        return Collection::make($data);
    }

    public function getUserInfo(string $user): Collection
    {
        $data = Cache::rememberForever(
            'user.info.'.$user,
            fn () => $this->getRequest(
                method: 'user.getinfo',
                params: [
                    'user' => $user,
                ],
                dataKey: 'user'
            ));

        return Collection::make($data);
    }

    public function getWeeklyTrackList(string $user, int $from, int $to): Collection
    {
        $data = Cache::rememberForever(
            'user.weeklytrackchart.'.$user.'.'.$from.'.'.$to,
            fn () => $this->getRequest(
            method: 'user.getweeklytrackchart',
            params: [
                'user' => $user,
                'from' => $from,
                'to' => $to,
            ],
            dataKey: 'weeklytrackchart.track'
        ));

        return Collection::make($data);
    }

    public function getAlbumInfo(string $artist, string $album): Collection
    {
        $data = Cache::rememberForever(
            'album.info.'.md5($artist.'|'.$album),
            fn () => $this->getRequest(
            method: 'album.getinfo',
            params: [
                'artist' => $artist,
                'album' => $album,
            ],
            dataKey: 'album'
        ));

        return Collection::make($data);
    }

    public function getTrackInfo(string $artist, string $track): Collection
    {
        $data = Cache::rememberForever(
            'track.info.'.md5($artist.'|'.$track),
            fn () => $this->getRequest(
            method: 'track.getinfo',
            params: [
                'artist' => $artist,
                'track' => $track,
            ],
            dataKey: 'track.album'
        ));

        return Collection::make($data);
    }
}
