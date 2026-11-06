<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LastFm\FetchWeeklyTracksAction;
use App\Actions\LastFm\PersistWeeklyChartAction;
use App\Clients\LastFmClient;
use App\Data\LastFm\LastFmUserData;
use App\Data\LastFm\WeeklyChartPeriodData;
use App\Data\LastFm\WeeklyTrackData;
use App\Facades\LastFm;
use App\Models\LastFmChart;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function sprintf;

#[Signature('lastfm:sync-weekly {user?} {--limit=}')]
#[Description('Sync weekly data from Last.fm')]
final class LastFmSyncWeekly extends Command
{
    private array $dbQueries = [];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $user = $this->argument('user') ?? LastFm::getDefaultUser();
        $limit = (int) ($this->option('limit') ?? config('services.lastfm.top_songs'));

        if (! $user) {
            $this->error('No se especificó un usuario');

            return;
        }

        if (config('services.lastfm.show_logs')) {
            $this->activateQueryLog();
        }

        $this->info('Sincronizando Last.fm para el usuario: '.$user);

        $userDto = LastFmUserData::fromApi(LastFm::getUserInfo($user));
        $registered = $userDto->registeredUnixtime;
        $this->info('Usuario registrado desde: '.$registered);

        $chartsToSync = LastFm::getWeeklyChartList($user)
            ->map(fn (array $chart): WeeklyChartPeriodData => WeeklyChartPeriodData::fromApi($chart))
            ->filter(fn (WeeklyChartPeriodData $chart): bool => $chart->to >= $registered);

        $existingCharts = LastFmChart::existingCharts($user, $limit);

        $start = microtime(true);
        foreach ($chartsToSync as $chart) {
            $lastFmChart = $existingCharts->get($chart->from);

            if ($lastFmChart?->synced) {
                $this->info('Semana ya sincronizada: '.$this->chartPeriod($lastFmChart));

                continue;
            }

            if (! $lastFmChart) {
                $lastFmChart = LastFmChart::forChart((string) $chart->from, (string) $chart->to, $limit, $user);
            }

            try {
                $weeklyTrackList = FetchWeeklyTracksAction::run($user, $limit, $lastFmChart);
            } catch (Exception $exception) {
                Log::error($exception->getMessage());
                $this->error($exception->getMessage());
                $this->error('Error al sincronizar semana: '.$this->chartPeriod($lastFmChart));
                $this->newLine();

                continue;
            }

            if ($weeklyTrackList->isEmpty()) {
                $lastFmChart->markAsSynced();
                $this->newLine();
                $this->error('Semana sin canciones: '.$this->chartPeriod($lastFmChart));
                $this->newLine();

                continue;
            }

            PersistWeeklyChartAction::run($lastFmChart, $weeklyTrackList);

            $lastFmChart->markAsSynced();
            $this->renderWeeklyStats($lastFmChart, $weeklyTrackList);
        }

        $this->info('Tiempo total: '.(microtime(true) - $start).' segundos');
    }

    private function activateQueryLog(): void
    {
        DB::listen(function ($query): void {
            $sql = mb_trim($query->sql);
            $type = mb_strtoupper(strtok($sql, ' ') ?: 'OTHER');

            if (str_contains($sql, '`cache`')) {
                $type = 'CACHE '.$type;
            }

            $this->dbQueries[$type] = ($this->dbQueries[$type] ?? 0) + 1;
        });
    }

    private function chartPeriod(LastFmChart $chart): string
    {
        return sprintf('%s - %s', $chart->from->format('Y-m-d'), $chart->to->format('Y-m-d'));
    }

    private function renderLogStats(): void
    {
        $log = LastFmClient::getCallLog();
        $apiCalls = LastFmClient::getCallCount();
        $dbCalls = array_sum($this->dbQueries);

        $this->info(sprintf('Estadísticas: 📡 %d llamadas a la API | 🗄️ %d consultas a la BD', $apiCalls, $dbCalls));

        $apiRows = $log->countBy('method')->map(fn ($count, $method): array => [
            '📡 '.$method,
            $count,
        ]);

        $this->table(
            ['Type', 'Count'],
            collect($this->dbQueries)->map(fn ($count, $type): array => [
                '🗄️ '.$type,
                $count,
            ]),
            'borderless'
        );

        $this->table(
            ['Operación', 'Cantidad'],
            $apiRows,
            'borderless'
        );
        $this->newLine();
    }

    /**
     * @param  Collection<int, WeeklyTrackData>  $weeklyTrackList
     */
    private function renderWeeklyStats(LastFmChart $lastFmChart, Collection $weeklyTrackList): void
    {
        $this->newLine(2);
        $this->warn(sprintf('Semana # %s: ', $lastFmChart->id).sprintf('%s. Songs %d', $this->chartPeriod($lastFmChart), $weeklyTrackList->count()));

        $this->table(
            ['Artist', 'Album', 'Track', 'Playcount'],
            $weeklyTrackList->map(fn (WeeklyTrackData $track): array => [
                'artist' => $track->artist,
                'album' => $track->album,
                'track' => $track->track,
                'playcount' => $track->playcount,
            ]),
            'borderless'
        );

        $this->newLine();

        if (config('services.lastfm.show_logs')) {
            $this->renderLogStats();
        }
    }
}
