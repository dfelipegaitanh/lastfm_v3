<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

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

    private function getRequest(string $method, array $params, ?string $dataKey): LazyCollection
    {
        $response = $this->client()->get('', array_merge([
            'method' => $method,
        ], $params));

        if (! $response->successful()) {
            return LazyCollection::make([]);
        }

        $data = $dataKey ? $response->json($dataKey) : $response->json();

        return LazyCollection::make(is_array($data) ? $data : []);
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

    public function getArtistInfo(string $artist): LazyCollection
    {
        return $this->getRequest('artist.getinfo', ['artist' => $artist], 'artist');
    }

    public function getWeeklyChartList(string $user): LazyCollection
    {
        return $this->getRequest(
            method: 'user.getweeklychartlist',
            params: ['user' => $user],
            dataKey: 'weeklychartlist.chart'
        );
    }

    public function getUserInfo(string $user): LazyCollection
    {
        return $this->getRequest(
            method: 'user.getinfo',
            params: [
                'user' => $user,
            ],
            dataKey: 'user'
        );
    }

    public function getWeeklyTrackList(string $user, int $from, int $to): LazyCollection
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
}
