<?php

namespace App\Services;

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

    /**
     * Get the default user from config.
     */
    public function getDefaultUser(): ?string
    {
        return $this->defaultUser;
    }

    /**
     * Build the base HTTP client with default parameters required by Last.fm API.
     * 
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function client()
    {
        return Http::baseUrl($this->baseUrl)->withQueryParameters([
            'api_key' => $this->apiKey,
            'format' => 'json',
        ]);
    }

    /**
     * Example method to get info for an artist.
     * 
     * @param string $artist
     * @return array|null
     */
    public function getArtistInfo(string $artist): ?array
    {
        $response = $this->client()->get('', [
            'method' => 'artist.getinfo',
            'artist' => $artist,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    // Add more Last.fm API methods here as needed...
}
