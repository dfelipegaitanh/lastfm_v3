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

    private function getRequest(string $method, array $params = [], string $dataKey = null): ?Collection
    {
        $response = $this->client()->get('', array_merge([
            'method' => $method,
        ], $params));

        if (!$response->successful()) {
            return null;
        }

        $data = $dataKey ? $response->json($dataKey) : $response->json();

        return $data ? collect($data) : null;
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

    public function getArtistInfo(string $artist): ?Collection
    {
        return $this->getRequest('artist.getinfo', ['artist' => $artist], 'artist');
    }

    public function getWeeklyChartList(string $user): ?Collection
    {
        return $this->getRequest(
            method: 'user.getweeklychartlist',
            params: ['user' => $user],
            dataKey: 'weeklychartlist.chart'
        );
    }
}
