<?php

namespace App\Console\Commands;

use App\Facades\LastFm;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('lastfm:sync-weekly {user?}')]
#[Description('Sync weekly data from Last.fm')]
class LastFmSyncWeekly extends Command
{
    private string $user;

    /**
     * Execute the console command.
     */
    public function handle(): void
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
                    $this->info("Semana ya sincronizada: {$this->chartPeriod($lastFmChart)}");

                    return;
                }

                try {
                    $weeklyTrackList = $this->getWeeklyTrackList($user, $lastFmChart);
                } catch (\Exception) {
                    $this->error("Error al sincronizar semana: {$this->chartPeriod($lastFmChart)}");

                    return;
                }

                if ($weeklyTrackList->isEmpty()) {
                    $lastFmChart->markAsSynced();
                    $this->error("Semana sin canciones: {$this->chartPeriod($lastFmChart)}");

                    return;

                }

                $this->persistTrackList($lastFmChart, $weeklyTrackList);

                $this->table(
                    ['Artist', 'Track', 'Playcount'],
                    $weeklyTrackList->map(fn ($track) => [
                        'artist' => $track['artist'],
                        'track' => $track['track'],
                        'playcount' => $track['playcount'],
                    ])
                );

                $this->info("Sincronizando semana: {$this->chartPeriod($lastFmChart)}. Songs {$weeklyTrackList->count()}");

                $lastFmChart->markAsSynced();

            });

    }

    private function chartPeriod(LastFmChart $chart): string
    {
        return "{$chart->from->format('Y-m-d')} - {$chart->to->format('Y-m-d')}";
    }

    protected function getWeeklyTrackList(string $user, LastFmChart $lastFmChart): Collection
    {

        return LastFm::getWeeklyTrackList($user, $lastFmChart->from->timestamp, $lastFmChart->to->timestamp)
            ->filter(fn ($track) => ($track['@attr']['rank'] ?? 0) <= config('services.lastfm.top_songs'))
            ->map(function ($track) {


                $artist = $track['artist']['#text'] ?? '';
                $trackName = $track['name'] ?? '';
                $rank = $track['@attr']['rank'] ?? 0;
                $album = '';
                $albumMbid = '';

                if ($artist && $trackName) {
                    $lastFmTrackAlbum = LastFm::getTrackInfo($artist, $trackName);

                    $album = $lastFmTrackAlbum->get('title');
                    $albumMbid = $lastFmTrackAlbum->get('mbid');

                    $this->info("Album: {$album}. Track: {$trackName}. Artist: {$artist}. Rank: {$rank}");
                }

                return [
                    'artist' => $artist,
                    'artist_mbid' => $track['artist']['mbid'] ?? '',
                    'track' => $trackName,
                    'track_mbid' => $track['mbid'] ?? '',
                    'playcount' => $track['playcount'] ?? 0,
                    'rank' => $rank,
                    'album' => $album,
                    'album_mbid' => $albumMbid,
                ];
            })->collect();

    }

    protected function persistTrackList(LastFmChart $lastFmChart, Collection $weeklyTrackList)
    {
        $weeklyTrackList->each(function ($track) use ($lastFmChart) {

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
                'rank' => $track['rank'],
            ]);

        });
    }
}
