<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Clients\LastFmClient;
use App\Facades\LastFm;
use App\Models\LastFmAlbum;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmTag;
use App\Models\LastFmTrack;
use App\Models\LastFmTrackPlaycounts;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('lastfm:sync-weekly {user?}')]
#[Description('Sync weekly data from Last.fm')]
final class LastFmSyncWeekly extends Command
{
    private array $albumIdMap = [];

    private array $artistIdMap = [];

    private array $tagIdMap = [];

    private array $trackIdMap = [];

    private array $trackTagsSyncedMap = [];

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

        $dbQueries = [];
        DB::listen(function ($query) use (&$dbQueries): void {
            $type = mb_strtoupper(explode(' ', mb_trim($query->sql))[0] ?? 'OTHER');
            $dbQueries[$type] = ($dbQueries[$type] ?? 0) + 1;
        });

        $this->info('Sincronizando Last.fm para el usuario: '.$user);

        $userData = LastFm::getUserInfo($user);

        $registered = data_get($userData->get('registered'), 'unixtime');
        $this->info('Usuario registrado desde: '.$registered);

        $weeklyChartList = LastFm::getWeeklyChartList($user);
        $chartsToSync = $weeklyChartList->filter(fn ($chart): bool => $chart['to'] >= $registered);

        $existingCharts = LastFmChart::existingCharts($user);

        $chartsToSync->each(function (array $chart) use ($user, &$dbQueries, $existingCharts): void {
            $lastFmChart = $existingCharts->get($chart['from']);

            if ($lastFmChart && $lastFmChart->synced) {
                $this->info('Semana ya sincronizada: '.$this->chartPeriod($lastFmChart));

                return;
            }

            if (! $lastFmChart) {
                $lastFmChart = LastFmChart::forChart($chart['from'], $chart['to'], $user);
            }

            try {
                $weeklyTrackList = $this->getWeeklyTrackList($user, $lastFmChart);
            } catch (Exception $exception) {
                Log::error($exception->getMessage());
                $this->error($exception->getMessage());
                $this->error('Error al sincronizar semana: '.$this->chartPeriod($lastFmChart));
                $this->newLine();

                return;
            }

            if ($weeklyTrackList->isEmpty()) {
                $lastFmChart->markAsSynced();
                $this->newLine();
                $this->error('Semana sin canciones: '.$this->chartPeriod($lastFmChart));
                $this->newLine();

                return;

            }

            $this->persistTrackList($lastFmChart, $weeklyTrackList);

            $this->newLine(2);
            $this->warn('Semana # '.$lastFmChart->id.': '.sprintf('%s. Songs %d', $this->chartPeriod($lastFmChart), $weeklyTrackList->count()));

            $this->table(
                ['Artist', 'Album', 'Track', 'Playcount'],
                $weeklyTrackList->map(fn ($track): array => [
                    'artist' => $track['artist'],
                    'album' => $track['album'],
                    'track' => $track['track'],
                    'playcount' => $track['playcount'],
                ]),
                'borderless'
            );

            $this->newLine();
            $lastFmChart->markAsSynced();
            $log = LastFmClient::getCallLog();

            $apiCalls = LastFmClient::getCallCount();
            $dbCalls = array_sum($dbQueries);

            $this->info(sprintf('Estadísticas: 📡 %d llamadas a la API | 🗄️ %d consultas a la BD', $apiCalls, $dbCalls));

            $apiRows = $log->countBy('method')->map(fn ($count, $method): array => [
                '📡 '.$method,
                $count,
            ]);

            $dbRows = collect($dbQueries)->map(fn ($count, $type): array => [
                '🗄️ '.$type,
                $count,
            ]);

            $this->table(
                ['Operación', 'Cantidad'],
                $apiRows->concat($dbRows)->toArray(),
                'borderless'
            );
            $this->newLine();

        });

    }

    private function chartPeriod(LastFmChart $chart): string
    {
        return sprintf('%s - %s', $chart->from->format('Y-m-d'), $chart->to->format('Y-m-d'));
    }

    private function getCachedAlbumId(int $lastFmArtistId, array $album): int
    {

        $key = $lastFmArtistId.'|'.$album['album'];

        return $this->albumIdMap[$key] ??= LastFmAlbum::firstOrCreate([
            'last_fm_artist_id' => $lastFmArtistId,
            'name' => $album['album'],
            'mbid' => $album['album_mbid'],
        ])->id;
    }

    private function getCachedArtistId(array $track): int
    {
        $key = $track['artist'].'|'.$track['artist_mbid'];

        return $this->artistIdMap[$key] ??= LastFmArtist::firstOrCreate(
            ['name' => $track['artist']],
            ['mbid' => $track['artist_mbid']]
        )->id;
    }

    private function getCachedTagId(string $tagName): int
    {
        return $this->tagIdMap[$tagName] ??= LastFmTag::firstOrCreate(['name' => $tagName])->id;
    }

    private function getCachedTrack(array $track): int
    {

        $lastFmArtistId = $this->getCachedArtistId($track);
        $lastFmAlbumId = $this->getCachedAlbumId($lastFmArtistId, $track);

        $key = $lastFmArtistId.'|'.$lastFmAlbumId.'|'.$track['track'];

        return $this->trackIdMap[$key] ??= LastFmTrack::firstOrCreate([
            'last_fm_artist_id' => $lastFmArtistId,
            'last_fm_album_id' => $lastFmAlbumId,
            'name' => $track['track'],
            'mbid' => $track['track_mbid'],
        ])->id;
    }

    private function getWeeklyTrackList(string $user, LastFmChart $lastFmChart): Collection
    {

        return LastFm::getWeeklyTrackList($user, $lastFmChart->from->timestamp, $lastFmChart->to->timestamp)
            ->filter(fn ($track): bool => ($track['@attr']['rank'] ?? 0) <= config('services.lastfm.top_songs'))
            ->map(function (array $track): array {

                $artist = $track['artist']['#text'] ?? '';
                $trackName = $track['name'] ?? '';
                $rank = $track['@attr']['rank'] ?? 0;
                $albumTitle = '';
                $albumMbid = '';

                if ($artist && $trackName) {
                    $lastFmTrackInfo = LastFm::getTrackInfo($artist, $trackName);

                    $tags = collect(data_get($lastFmTrackInfo, 'toptags.tag', []));

                    $albumTitle = data_get($lastFmTrackInfo->get('album'), 'title', 'Unknown Album');
                    $albumMbid = data_get($lastFmTrackInfo->get('album'), 'mbid');

                }

                return [
                    'artist' => $artist,
                    'artist_mbid' => $track['artist']['mbid'] ?? '',
                    'track' => $trackName,
                    'track_mbid' => $track['mbid'] ?? '',
                    'playcount' => $track['playcount'] ?? 0,
                    'rank' => $rank,
                    'album' => $albumTitle,
                    'album_mbid' => $albumMbid,
                    'tags' => $tags->toArray(),
                ];
            })->collect();

    }

    private function persistTrackList(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        $playcountsToInsert = [];

        $weeklyTrackList->each(function (array $track) use ($lastFmChart, &$playcountsToInsert): void {

            $lastFmTrackId = $this->getCachedTrack($track);

            if (empty($this->trackTagsSyncedMap[$lastFmTrackId])) {

                if ($track['tags']) {

                    $tagIds = collect($track['tags'])
                        ->map(fn (array $tag): int => $this->getCachedTagId($tag['name']))
                        ->toArray();

                    LastFmTrack::find($lastFmTrackId)->tags()->syncWithoutDetaching($tagIds);
                }

                $this->trackTagsSyncedMap[$lastFmTrackId] = true;
            }

            $playcountsToInsert[] = [
                'last_fm_chart_id' => $lastFmChart->id,
                'last_fm_track_id' => $lastFmTrackId,
                'playcount' => $track['playcount'],
                'rank' => $track['rank'],
            ];

        });

        if ($playcountsToInsert !== []) {
            LastFmTrackPlaycounts::upsert(
                $playcountsToInsert,
                ['last_fm_chart_id', 'last_fm_track_id'],
                ['playcount', 'rank']
            );
        }
    }
}
