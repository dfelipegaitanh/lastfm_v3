<?php

namespace App\Console\Commands;

use App\Facades\LastFm;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lastfm:sync-weekly {user?}')]
#[Description('Sync weekly data from Last.fm')]
class lastfmSync_weekly extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = $this->argument('user') ?? LastFm::getDefaultUser();

        if (! $user) {
            $this->error('No se especificó un usuario');

            return;
        }

        $this->info("Sincronizando Last.fm para el usuario: {$user}");

        $userData = LastFm::getUserInfo($user);

        $registered = data_get($userData->get('registered'), 'unixtime');
        $this->info("Usuario registrado desde: {$registered}");

        $weeklyChartList = LastFm::getWeeklyChartList($user);
        $weeklyChartList->filter(fn ($chart) => $chart['to'] >= $registered)
            ->each(function ($chart) use ($user) {

                $lastFmChart = LastFmChart::forChart($chart['from'], $chart['to'], $user);

                if ($lastFmChart->synced) {
                    $this->info("Semana ya sincronizada: {$lastFmChart->from->format('Y-m-d')} - {$lastFmChart->to->format('Y-m-d')}");

                    return;
                }

                $weeklyTrackList = LastFm::getWeeklyTrackList($user, $chart['from'], $chart['to'])
                    ->filter(fn ($track) => ($track['playcount'] ?? 0) >= config('services.lastfm.top_songs'))
                    ->map(function ($track) {
                        return [
                            'artist' => $track['artist']['#text'] ?? '',
                            'artist_mbid' => $track['artist']['mbid'] ?? '',
                            'track' => $track['name'] ?? '',
                            'track_mbid' => $track['mbid'] ?? '',
                            'playcount' => $track['playcount'] ?? 0,
                        ];
                    })
                    ->each(function ($track) use ($lastFmChart) {

                        $lastFmArtist = LastFmArtist::firstOrCreate([
                            'name' => $track['artist'],
                            'mbid' => $track['artist_mbid'],
                        ]);

                        $lastFmTrack = $lastFmArtist->tracks()->firstOrCreate([
                            'name' => $track['track'],
                            'mbid' => $track['track_mbid'],
                        ]);

                        $lastFmChart->trackPlaycounts()->firstOrCreate([
                            'last_fm_track_id' => $lastFmTrack->id,
                            'playcount' => $track['playcount'],
                        ]);

                    });

                if ($weeklyTrackList->isEmpty()) {
                    $lastFmChart->markAsSynced();
                    $this->error("Semana sin canciones: {$lastFmChart->from->format('Y-m-d')} - {$lastFmChart->to->format('Y-m-d')}");

                    return;

                }

                $this->table(
                    ['Artist', 'Track', 'Playcount'],
                    $weeklyTrackList->map(fn ($track) => [
                        'artist' => $track['artist'],
                        'track' => $track['track'],
                        'playcount' => $track['playcount'],
                    ])
                );

                $this->info("Sincronizando semana: {$lastFmChart->from->format('Y-m-d')} - {$lastFmChart->to->format('Y-m-d')}. Songs {$weeklyTrackList->count()}");

                $lastFmChart->markAsSynced();

            });

    }
}
