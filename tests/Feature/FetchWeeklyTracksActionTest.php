<?php

declare(strict_types=1);

use App\Actions\LastFm\FetchWeeklyTracksAction;
use App\Clients\LastFmClient;
use App\Data\LastFm\WeeklyTrackData;
use App\Models\LastFmChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    LastFmClient::resetCallLog();
});

test('fetches weekly tracks and transforms them into WeeklyTrackData DTOs respecting limit', function (): void {
    Http::fake([
        '*method=user.getweeklytrackchart*' => Http::response([
            'weeklytrackchart' => [
                'track' => [
                    [
                        'artist' => ['#text' => 'Radiohead', 'mbid' => 'art-mbid-1'],
                        'name' => 'Karma Police',
                        'mbid' => 'trk-mbid-1',
                        'playcount' => '25',
                        '@attr' => ['rank' => '1'],
                    ],
                    [
                        'artist' => ['#text' => 'Portishead', 'mbid' => 'art-mbid-2'],
                        'name' => 'Glory Box',
                        'mbid' => 'trk-mbid-2',
                        'playcount' => '20',
                        '@attr' => ['rank' => '2'],
                    ],
                    [
                        'artist' => ['#text' => 'Massive Attack', 'mbid' => 'art-mbid-3'],
                        'name' => 'Teardrop',
                        'mbid' => 'trk-mbid-3',
                        'playcount' => '15',
                        '@attr' => ['rank' => '3'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*artist=Radiohead*' => Http::response([
            'track' => [
                'album' => ['title' => 'OK Computer', 'mbid' => 'alb-mbid-1'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'alternative'],
                        ['name' => 'rock'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*artist=Portishead*' => Http::response([
            'track' => [
                'album' => ['title' => 'Dummy', 'mbid' => 'alb-mbid-2'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'trip hop'],
                    ],
                ],
            ],
        ]),
    ]);

    $chart = LastFmChart::create([
        'from' => '1735689600',
        'to' => '1736294400',
        'user' => 'svigle',
        'play_count_limit' => 2,
        'viral_limit' => 0,
    ]);

    $tracks = FetchWeeklyTracksAction::run($chart);

    expect($tracks)->toHaveCount(2)
        ->and($tracks->every(fn ($item): bool => $item instanceof WeeklyTrackData))->toBeTrue();

    $first = $tracks->first();
    expect($first->artist)->toBe('Radiohead')
        ->and($first->artistMbid)->toBe('art-mbid-1')
        ->and($first->track)->toBe('Karma Police')
        ->and($first->album)->toBe('OK Computer')
        ->and($first->albumMbid)->toBe('alb-mbid-1')
        ->and($first->playcount)->toBe(25)
        ->and($first->rank)->toBe(1)
        ->and($first->tags)->toBe(['alternative', 'rock']);

    $second = $tracks->last();
    expect($second->artist)->toBe('Portishead')
        ->and($second->album)->toBe('Dummy')
        ->and($second->tags)->toBe(['trip hop'])
        ->and($second->rank)->toBe(2);
});

test('filters weekly tracks by viral limit', function (): void {
    Http::fake([
        '*method=user.getweeklytrackchart*' => Http::response([
            'weeklytrackchart' => [
                'track' => [
                    [
                        'artist' => ['#text' => 'Radiohead', 'mbid' => 'art-mbid-1'],
                        'name' => 'Karma Police',
                        'mbid' => 'trk-mbid-1',
                        'playcount' => '25',
                        '@attr' => ['rank' => '1'],
                    ],
                    [
                        'artist' => ['#text' => 'Portishead', 'mbid' => 'art-mbid-2'],
                        'name' => 'Glory Box',
                        'mbid' => 'trk-mbid-2',
                        'playcount' => '20',
                        '@attr' => ['rank' => '2'],
                    ],
                    [
                        'artist' => ['#text' => 'Massive Attack', 'mbid' => 'art-mbid-3'],
                        'name' => 'Teardrop',
                        'mbid' => 'trk-mbid-3',
                        'playcount' => '5',
                        '@attr' => ['rank' => '3'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*artist=Radiohead*' => Http::response([
            'track' => [
                'album' => ['title' => 'OK Computer', 'mbid' => 'alb-mbid-1'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'rock'],
                    ],
                ],
            ],
        ]),
        '*method=track.getinfo*artist=Portishead*' => Http::response([
            'track' => [
                'album' => ['title' => 'Dummy', 'mbid' => 'alb-mbid-2'],
                'toptags' => [
                    'tag' => [
                        ['name' => 'trip hop'],
                    ],
                ],
            ],
        ]),
    ]);

    $chart = LastFmChart::create([
        'from' => '1735689600',
        'to' => '1736294400',
        'user' => 'svigle',
        'play_count_limit' => 5,
        'viral_limit' => 20,
    ]);

    $tracks = FetchWeeklyTracksAction::run($chart);

    expect($tracks)->toHaveCount(2)
        ->and($tracks->pluck('track')->all())->toBe(['Karma Police', 'Glory Box'])
        ->and($tracks->pluck('playcount')->all())->toBe([25, 20])
        ->and($tracks->every(fn (WeeklyTrackData $t): bool => $t->playcount >= 20))->toBeTrue()
        ->and($tracks->contains(fn (WeeklyTrackData $t): bool => $t->track === 'Teardrop'))->toBeFalse();
});
