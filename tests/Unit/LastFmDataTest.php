<?php

declare(strict_types=1);

use App\Data\LastFm\LastFmUserData;
use App\Data\LastFm\WeeklyChartPeriodData;
use App\Data\LastFm\WeeklyTrackData;

describe('WeeklyTrackData', function (): void {
    test('creates DTO from API payload with complete track and trackInfo', function (): void {
        $track = [
            'artist' => [
                '#text' => 'Radiohead',
                'mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
            ],
            'name' => 'Karma Police',
            'mbid' => 'b84c2c8f-82b6-5122-0552-e1c6f5233822',
            'playcount' => '42',
            '@attr' => ['rank' => '1'],
        ];

        $trackInfo = [
            'album' => [
                'title' => 'OK Computer',
                'mbid' => 'c95d3d90-93c7-6233-1663-f2d7a6344933',
            ],
            'toptags' => [
                'tag' => [
                    ['name' => 'alternative rock'],
                    ['name' => 'indie rock'],
                ],
            ],
        ];

        $dto = WeeklyTrackData::fromApi($track, $trackInfo);

        expect($dto->artist)->toBe('Radiohead')
            ->and($dto->artistMbid)->toBe('a74b1b7f-71a5-4011-9441-d0b5e4122711')
            ->and($dto->track)->toBe('Karma Police')
            ->and($dto->trackMbid)->toBe('b84c2c8f-82b6-5122-0552-e1c6f5233822')
            ->and($dto->album)->toBe('OK Computer')
            ->and($dto->albumMbid)->toBe('c95d3d90-93c7-6233-1663-f2d7a6344933')
            ->and($dto->playcount)->toBe(42)
            ->and($dto->rank)->toBe(1)
            ->and($dto->tags)->toBe(['alternative rock', 'indie rock']);
    });

    test('falls back to Unknown Album and null mbids when not provided', function (): void {
        $track = [
            'artist' => ['#text' => 'Unknown Artist', 'mbid' => ''],
            'name' => 'Untitled Track',
            'mbid' => '',
            'playcount' => 10,
            '@attr' => ['rank' => 3],
        ];

        $trackInfo = [
            'album' => [],
            'toptags' => [],
        ];

        $dto = WeeklyTrackData::fromApi($track, $trackInfo);

        expect($dto->album)->toBe('Unknown Album')
            ->and($dto->albumMbid)->toBeNull()
            ->and($dto->artistMbid)->toBeNull()
            ->and($dto->trackMbid)->toBeNull()
            ->and($dto->tags)->toBe([]);
    });

    test('normalizes single tag dictionary in trackInfo to list of strings', function (): void {
        $track = [
            'artist' => ['#text' => 'Daft Punk'],
            'name' => 'One More Time',
        ];

        $trackInfo = [
            'album' => ['title' => 'Discovery'],
            'toptags' => [
                'tag' => ['name' => 'electronic'],
            ],
        ];

        $dto = WeeklyTrackData::fromApi($track, $trackInfo);

        expect($dto->tags)->toBe(['electronic'])
            ->and($dto->album)->toBe('Discovery');
    });

    test('normalizes tags from collection or string list when trackInfo is null', function (): void {
        $track = [
            'artist' => 'The Strokes',
            'track' => 'Reptilia',
            'album' => 'Room on Fire',
            'album_mbid' => 'some-album-mbid',
            'tags' => ['garage rock', 'indie'],
            'playcount' => 5,
            'rank' => 2,
        ];

        $dto = WeeklyTrackData::fromApi($track);

        expect($dto->artist)->toBe('The Strokes')
            ->and($dto->track)->toBe('Reptilia')
            ->and($dto->album)->toBe('Room on Fire')
            ->and($dto->albumMbid)->toBe('some-album-mbid')
            ->and($dto->tags)->toBe(['garage rock', 'indie'])
            ->and($dto->playcount)->toBe(5)
            ->and($dto->rank)->toBe(2);
    });
});

describe('WeeklyChartPeriodData', function (): void {
    test('creates DTO from API payload casting timestamps to int', function (): void {
        $dto = WeeklyChartPeriodData::fromApi([
            'from' => '1108296000',
            'to' => '1108900800',
        ]);

        expect($dto->from)->toBe(1108296000)
            ->and($dto->to)->toBe(1108900800);
    });

    test('defaults missing values to 0', function (): void {
        $dto = WeeklyChartPeriodData::fromApi([]);

        expect($dto->from)->toBe(0)
            ->and($dto->to)->toBe(0);
    });
});

describe('LastFmUserData', function (): void {
    test('creates DTO from array payload', function (): void {
        $dto = LastFmUserData::fromApi([
            'name' => 'svigle',
            'playcount' => '98765',
            'registered' => [
                'unixtime' => '1108296000',
            ],
        ]);

        expect($dto->name)->toBe('svigle')
            ->and($dto->registeredUnixtime)->toBe(1108296000)
            ->and($dto->playcount)->toBe(98765);
    });

    test('creates DTO from Collection instance', function (): void {
        $collection = collect([
            'name' => 'johndoe',
            'playcount' => 1234,
            'registered' => [
                'unixtime' => 1234567890,
            ],
        ]);

        $dto = LastFmUserData::fromApi($collection);

        expect($dto->name)->toBe('johndoe')
            ->and($dto->registeredUnixtime)->toBe(1234567890)
            ->and($dto->playcount)->toBe(1234);
    });
});
