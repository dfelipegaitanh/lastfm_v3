<?php

declare(strict_types=1);

use App\Enums\LastFmChartPresetType;
use Inertia\Testing\AssertableInertia as Assert;

test('welcome page renders hardcoded lastfm stats structured around domain models', function (): void {
    config([
        'services.lastfm.user' => 'svigle',
        'services.lastfm.top_songs' => 10,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('welcome')
            ->has('chartSummary', fn (Assert $summary): Assert => $summary
                ->where('id', 1)
                ->where('user', 'svigle')
                ->where('from', '2026-11-16 00:00:00')
                ->where('to', '2026-11-23 00:00:00')
                ->where('synced', true)
                ->where('preset_type', LastFmChartPresetType::Top->value)
                ->where('play_count_limit', 10)
                ->where('total_charts_synced', 52)
                ->where('total_playcount', 218)
            )
            ->has('tracks', 8)
            ->has('tracks.0', fn (Assert $track): Assert => $track
                ->where('id', 101)
                ->where('rank', 1)
                ->where('playcount', 42)
                ->where('track_name', 'Master of Puppets')
                ->where('track_mbid', '4f7c1065-6261-4259-99e7-3e3f91916818')
                ->where('last_fm_artist_id', 1)
                ->where('artist_name', 'Metallica')
                ->where('artist_mbid', '65f4f0c5-ef9e-490c-aee3-909e7ae6b2ab')
                ->where('artist_image_url', '/images/artists/metallica/1.jpg')
                ->where('last_fm_album_id', 10)
                ->where('album_name', 'Master of Puppets (Remastered)')
                ->where('album_mbid', 'fed37cfc-2a6d-4569-9ac0-501a7c7598eb')
                ->where('album_image_url', '/images/artists/metallica/1.jpg')
                ->where('tags', ['thrash metal', 'heavy metal', '80s'])
            )
            ->has('tracks.4', fn (Assert $track): Assert => $track
                ->where('id', 105)
                ->where('rank', 5)
                ->where('artist_name', 'Radiohead')
                ->where('artist_image_url', null)
                ->where('album_image_url', null)
                ->etc()
            )
        );
});
