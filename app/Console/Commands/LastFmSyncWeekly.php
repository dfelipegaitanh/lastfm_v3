<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\LastFm;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Services\LastFmClient;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('lastfm:sync-weekly {user?}')]
#[Description('Sync weekly data from Last.fm')]
final class LastFmSyncWeekly extends Command
{
    private array $artistIdMap = [];

    private array $trackIdMap = [];

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

        $this->info('Sincronizando Last.fm para el usuario: ' . $user);

        $userData = LastFm::getUserInfo($user);

        $registered = data_get($userData->get('registered'), 'unixtime');
        $this->info('Usuario registrado desde: ' . $registered);

        $weeklyChartList = LastFm::getWeeklyChartList($user);
        $chartsToSync = $weeklyChartList->filter(fn ($chart): bool => $chart['to'] >= $registered);

        $this->withProgressBar($chartsToSync, function (array $chart) use ($user): void {

            $lastFmChart = LastFmChart::forChart($chart['from'], $chart['to'], $user);

            if ($lastFmChart->synced) {
                $this->info('Semana ya sincronizada: ' . $this->chartPeriod($lastFmChart));

                return;
            }

            try {
                $weeklyTrackList = $this->getWeeklyTrackList($user, $lastFmChart);
            } catch (Exception) {
                $this->error('Error al sincronizar semana: ' . $this->chartPeriod($lastFmChart));

                return;
            }

            if ($weeklyTrackList->isEmpty()) {
                $lastFmChart->markAsSynced();
                $this->newLine();
                $this->error('Semana sin canciones: ' . $this->chartPeriod($lastFmChart));

                return;

            }

            $this->persistTrackList($lastFmChart, $weeklyTrackList);

            $this->table(
                ['Artist', 'Track', 'Playcount'],
                $weeklyTrackList->map(fn ($track): array => [
                    'artist' => $track['artist'],
                    'track' => $track['track'],
                    'playcount' => $track['playcount'],
                ]),
                'borderless'
            );

            $this->info(sprintf('Sincronizando semana: %s. Songs %d', $this->chartPeriod($lastFmChart), $weeklyTrackList->count()));

            $lastFmChart->markAsSynced();

            $callCount = LastFmClient::getCallCount();
            $this->newLine();
            $this->info('📡 Total de llamadas reales a la API de Last.fm: ' . $callCount);

            $log = LastFmClient::getCallLog();
            $this->table(
                ['Method', 'Count'],
                $log->countBy('method')->map(fn ($count, $method): array => [
                    $method,
                    $count,
                ]),
                'borderless'
            );
            $this->newLine(2);

        });

    }

    private function chartPeriod(LastFmChart $chart): string
    {
        return sprintf('%s - %s', $chart->from->format('Y-m-d'), $chart->to->format('Y-m-d'));
    }

    private function getCachedArtist(array $track): LastFmArtist
    {
        return $this->artistIdMap[$track['artist']] ??= LastFmArtist::firstOrCreate(
            ['name' => $track['artist']],
            ['mbid' => $track['artist_mbid']]
        );
    }

    private function getCachedTrack(LastFmArtist $lastFmArtist, array $track)
    {
        $key = sprintf('%s|%s', $lastFmArtist->id, $track['track']);

        return $this->trackIdMap[$key] ??= $lastFmArtist->tracks()->firstOrCreate([
            'name' => $track['track'],
            'mbid' => $track['track_mbid'],
        ]);
    }

    private function getWeeklyTrackList(string $user, LastFmChart $lastFmChart): Collection
    {

        return LastFm::getWeeklyTrackList($user, $lastFmChart->from->timestamp, $lastFmChart->to->timestamp)
            ->filter(fn ($track): bool => ($track['@attr']['rank'] ?? 0) <= config('services.lastfm.top_songs'))
            ->map(function (array $track): array {

                $artist = $track['artist']['#text'] ?? '';
                $trackName = $track['name'] ?? '';
                $rank = $track['@attr']['rank'] ?? 0;
                $album = '';
                $albumMbid = '';

                if ($artist && $trackName) {
                    $lastFmTrackInfo = LastFm::getTrackInfo($artist, $trackName);

                    $album = $lastFmTrackInfo->get('album');
                    $albumMbid = $lastFmTrackInfo->get('mbid');

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

    private function persistTrackList(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        $weeklyTrackList->each(function (array $track) use ($lastFmChart): void {

            $lastFmArtist = $this->getCachedArtist($track);

            $lastFmTrack = $this->getCachedTrack($lastFmArtist, $track);

            $lastFmChart->trackPlaycounts()->firstOrCreate([
                'last_fm_track_id' => $lastFmTrack->id,
                'playcount' => $track['playcount'],
                'rank' => $track['rank'],
            ]);

        });
    }
}
