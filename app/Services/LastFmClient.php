<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

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

    private function getRequest(string $method, array $params, ?string $dataKey): Collection
    {
        $response = $this->client()->get('', array_merge([
            'method' => $method,
        ], $params));

        if (! $response->successful()) {
            return Collection::make([]);
        }

        $data = $dataKey ? $response->json($dataKey) : $response->json();

        return Collection::make(is_array($data) ? $data : []);
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

    public function getArtistInfo(string $artist): Collection
    {
        return $this->getRequest('artist.getinfo', ['artist' => $artist], 'artist');
    }

    public function getWeeklyChartList(string $user): Collection
    {
        return $this->getRequest(
            method: 'user.getweeklychartlist',
            params: ['user' => $user],
            dataKey: 'weeklychartlist.chart'
        );
    }

    public function getUserInfo(string $user): Collection
    {
        return $this->getRequest(
            method: 'user.getinfo',
            params: [
                'user' => $user,
            ],
            dataKey: 'user'
        );
    }

    public function getWeeklyTrackList(string $user, int $from, int $to): Collection
    {
        return $this->getRequest(
            method: 'user.getweeklytrackchart',
            params: [
                'user' => $user,
                'from' => $from,
                'to' => $to,
            ],
            dataKey: 'weeklytrackchart.track'
        );
    }

    public function getAlbumInfo(string $artist, string $album): Collection
    {
        return $this->getRequest(
            method: 'album.getinfo',
            params: [
                'artist' => $artist,
                'album' => $album,
            ],
            dataKey: 'album'
        );
    }

    public function getTrackInfo(string $artist, string $track): Collection
    {
        return $this->getRequest(
            method: 'track.getinfo',
            params: [
                'artist' => $artist,
                'track' => $track,
            ],
            dataKey: 'track.album'
        );
    }
}
