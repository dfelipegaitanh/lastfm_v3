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

test('sync weekly command accepts viral-limit option and filters persisted tracks', function (): void {
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
                        'playcount' => '25',
                        '@attr' => ['rank' => '1'],
                    ],
                    [
                        'artist' => ['#text' => 'Portishead', 'mbid' => 'art-2'],
                        'name' => 'Glory Box',
                        'mbid' => 'trk-2',
                        'playcount' => '20',
                        '@attr' => ['rank' => '2'],
                    ],
                    [
                        'artist' => ['#text' => 'Massive Attack', 'mbid' => 'art-3'],
                        'name' => 'Teardrop',
                        'mbid' => 'trk-3',
                        'playcount' => '5',
                        '@attr' => ['rank' => '3'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*artist=Radiohead*' => Http::response([
            'track' => [
                'album' => ['title' => 'OK Computer', 'mbid' => 'alb-1'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'rock'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*artist=Portishead*' => Http::response([
            'track' => [
                'album' => ['title' => 'Dummy', 'mbid' => 'alb-2'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'trip hop'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('lastfm:sync-weekly svigle --limit=5 --viral-limit=20')
        ->assertSuccessful();

    $chart = LastFmChart::where('user', 'svigle')->first();
    expect($chart)->not->toBeNull()
        ->and($chart->viral_limit)->toBe(20)
        ->and((bool) $chart->synced)->toBeTrue();

    expect(LastFmTrack::where('name', 'Karma Police')->exists())->toBeTrue()
        ->and(LastFmTrack::where('name', 'Glory Box')->exists())->toBeTrue()
        ->and(LastFmTrack::where('name', 'Teardrop')->exists())->toBeFalse();

    expect($chart->trackPlaycounts)->toHaveCount(2);
});
