<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\LastFm\StatChartSummaryData;
use App\Data\LastFm\StatTrackRowData;
use App\Enums\LastFmChartPresetType;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        $user = (string) config('services.lastfm.user', 'svigle');
        $playCountLimit = (int) config('services.lastfm.top_songs', 10);

        $tracks = [
            new StatTrackRowData(
                id: 101,
                rank: 1,
                playcount: 42,
                track_name: 'Master of Puppets',
                track_mbid: '4f7c1065-6261-4259-99e7-3e3f91916818',
                last_fm_artist_id: 1,
                artist_name: 'Metallica',
                artist_mbid: '65f4f0c5-ef9e-490c-aee3-909e7ae6b2ab',
                artist_image_url: '/images/artists/metallica/1.jpg',
                last_fm_album_id: 10,
                album_name: 'Master of Puppets (Remastered)',
                album_mbid: 'fed37cfc-2a6d-4569-9ac0-501a7c7598eb',
                album_image_url: '/images/artists/metallica/1.jpg',
                tags: ['thrash metal', 'heavy metal', '80s'],
            ),
            new StatTrackRowData(
                id: 102,
                rank: 2,
                playcount: 37,
                track_name: 'SHEESH',
                track_mbid: 'a1b2c3d4-1111-4222-8333-444455556666',
                last_fm_artist_id: 2,
                artist_name: 'BABYMONSTER',
                artist_mbid: '9e8d7c6b-5a4f-3e2d-1c0b-a9b8c7d6e5f4',
                artist_image_url: '/images/artists/babymonster/1.png',
                last_fm_album_id: 20,
                album_name: 'BABYMONS7ER',
                album_mbid: '12345678-9abc-def0-1234-56789abcdef0',
                album_image_url: '/images/artists/babymonster/1.png',
                tags: ['k-pop', 'dance-pop', 'hip hop'],
            ),
            new StatTrackRowData(
                id: 103,
                rank: 3,
                playcount: 31,
                track_name: 'A Day in the Life',
                track_mbid: '87654321-4321-4321-8321-210987654321',
                last_fm_artist_id: 3,
                artist_name: 'The Beatles',
                artist_mbid: 'b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d',
                artist_image_url: '/images/artists/the-beatles/1.jpg',
                last_fm_album_id: 30,
                album_name: "Sgt. Pepper's Lonely Hearts Club Band",
                album_mbid: 'f0011223-3445-5667-7889-9aabbccddeef',
                album_image_url: '/images/artists/the-beatles/1.jpg',
                tags: ['classic rock', 'psychedelic rock', '60s'],
            ),
            new StatTrackRowData(
                id: 104,
                rank: 4,
                playcount: 28,
                track_name: 'Fade to Black',
                track_mbid: '2e936841-9134-4b88-bc92-447551105677',
                last_fm_artist_id: 1,
                artist_name: 'Metallica',
                artist_mbid: '65f4f0c5-ef9e-490c-aee3-909e7ae6b2ab',
                artist_image_url: '/images/artists/metallica/1.jpg',
                last_fm_album_id: 11,
                album_name: 'Ride the Lightning',
                album_mbid: '4540f3b1-8176-4c36-a970-898a3b8d608e',
                album_image_url: null,
                tags: ['thrash metal', 'power ballad', 'metal'],
            ),
            new StatTrackRowData(
                id: 105,
                rank: 5,
                playcount: 24,
                track_name: 'Paranoid Android',
                track_mbid: '33f5a989-6388-463a-8696-615b912b7a11',
                last_fm_artist_id: 4,
                artist_name: 'Radiohead',
                artist_mbid: 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
                artist_image_url: null,
                last_fm_album_id: 40,
                album_name: 'OK Computer',
                album_mbid: 'fba5f8fe-c6c8-4511-8562-c94f48269923',
                album_image_url: null,
                tags: ['alternative rock', 'art rock', '90s'],
            ),
            new StatTrackRowData(
                id: 106,
                rank: 6,
                playcount: 21,
                track_name: 'DRIP',
                track_mbid: null,
                last_fm_artist_id: 2,
                artist_name: 'BABYMONSTER',
                artist_mbid: '9e8d7c6b-5a4f-3e2d-1c0b-a9b8c7d6e5f4',
                artist_image_url: '/images/artists/babymonster/1.png',
                last_fm_album_id: 21,
                album_name: 'DRIP',
                album_mbid: null,
                album_image_url: '/images/artists/babymonster/1.png',
                tags: ['k-pop', 'pop'],
            ),
            new StatTrackRowData(
                id: 107,
                rank: 7,
                playcount: 19,
                track_name: 'Disorder',
                track_mbid: '7d139a3b-758d-4187-8099-d14138a16b25',
                last_fm_artist_id: 5,
                artist_name: 'Joy Division',
                artist_mbid: '9a58fda3-f4ed-4080-a3a5-f457aac9fcdd',
                artist_image_url: null,
                last_fm_album_id: 50,
                album_name: 'Unknown Pleasures',
                album_mbid: '35b82101-73c7-4484-9b1f-f658d2691e6e',
                album_image_url: null,
                tags: ['post-punk', 'new wave', '70s'],
            ),
            new StatTrackRowData(
                id: 108,
                rank: 8,
                playcount: 16,
                track_name: 'Tomorrow Never Knows',
                track_mbid: '5f12894a-1b2c-4d3e-8f9a-0b1c2d3e4f5a',
                last_fm_artist_id: 3,
                artist_name: 'The Beatles',
                artist_mbid: 'b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d',
                artist_image_url: '/images/artists/the-beatles/1.jpg',
                last_fm_album_id: 31,
                album_name: 'Revolver',
                album_mbid: '65432109-8765-4321-8765-432109876543',
                album_image_url: null,
                tags: ['psychedelic rock', 'experimental', '60s'],
            ),
        ];

        $totalPlaycount = array_sum(array_map(
            static fn (StatTrackRowData $track): int => $track->playcount,
            $tracks,
        ));

        $chartSummary = new StatChartSummaryData(
            id: 1,
            user: $user,
            from: '2026-11-16 00:00:00',
            to: '2026-11-23 00:00:00',
            synced: true,
            preset_type: LastFmChartPresetType::Top->value,
            play_count_limit: $playCountLimit,
            total_charts_synced: 52,
            total_playcount: $totalPlaycount,
        );

        return Inertia::render('welcome', [
            'chartSummary' => $chartSummary,
            'tracks' => $tracks,
        ]);
    }
}
