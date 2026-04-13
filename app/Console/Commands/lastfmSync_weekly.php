<?php

namespace App\Console\Commands;

use App\Facades\LastFm;
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

                $lastFmChart = LastFmChart::firstOrCreate([
                    'from' => $chart['from'],
                    'to' => $chart['to'],
                    'user' => $user,
                ]);

                if ($lastFmChart->synced) {
                    $this->info("Semana ya sincronizada: {$chart['from']} - {$chart['to']}");

                    return;
                }

                $weeklyTrackList = LastFm::getWeeklyTrackList($user, $chart['from'], $chart['to'])
                    ->filter(fn ($track) => ($track['playcount'] ?? 0) >= config('services.lastfm.top_songs'))
                    ->map(function ($track) {
                        return [
                            'artist' => $track['artist']['#text'] ?? '',
                            'track' => $track['name'] ?? '',
                            'playcount' => $track['playcount'] ?? 0,
                        ];
                    });
                // })->each(function ($track) {
                //     dd($track);
                // });

                $this->info("Sincronizando semana: {$chart['from']} - {$chart['to']}. Songs {$weeklyTrackList->count()}");

                $lastFmChart->synced = true;
                $lastFmChart->save();

            });

    }
}
