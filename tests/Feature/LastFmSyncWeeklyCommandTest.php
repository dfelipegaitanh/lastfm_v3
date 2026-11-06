<?php

declare(strict_types=1);

use App\Actions\LastFm\PersistWeeklyChartAction;
use App\Clients\LastFmClient;
use App\Models\LastFmChart;
use App\Models\LastFmTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    LastFmClient::resetCallLog();
    PersistWeeklyChartAction::resetCacheMaps();
});

test('sync weekly command executes full pipeline using DTOs', function (): void {
    Http::fake([
        '*method=user.getinfo*' => Http::response([
            'user' => [
                'name' => 'svigle',
                'playcount' => '500',
                'registered' => [
                    'unixtime' => '1735689600',
                ],
            ],
        ]),
        '*method=user.getweeklychartlist*' => Http::response([
            'weeklychartlist' => [
                'chart' => [
                    [
                        'from' => '1735689600',
                        'to' => '1736294400',
                    ],
                ],
            ],
        ]),
        '*method=user.getweeklytrackchart*' => Http::response([
            'weeklytrackchart' => [
                'track' => [
                    [
                        'artist' => ['#text' => 'Radiohead', 'mbid' => 'art-1'],
                        'name' => 'Karma Police',
                        'mbid' => 'trk-1',
                        'playcount' => '30',
                        '@attr' => ['rank' => '1'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*' => Http::response([
            'track' => [
                'album' => ['title' => 'OK Computer', 'mbid' => 'alb-1'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'rock'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('lastfm:sync-weekly svigle --limit=5')
        ->expectsOutputToContain('Sincronizando Last.fm para el usuario: svigle')
        ->expectsOutputToContain('Usuario registrado desde: 1735689600')
        ->assertSuccessful();

    $chart = LastFmChart::where('user', 'svigle')->first();
    expect($chart)->not->toBeNull()
        ->and((bool) $chart->synced)->toBeTrue();

    expect(LastFmTrack::where('name', 'Karma Police')->exists())->toBeTrue();
});
