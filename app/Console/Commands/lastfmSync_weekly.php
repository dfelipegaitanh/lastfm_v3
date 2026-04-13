<?php

namespace App\Console\Commands;

use App\Facades\LastFm;
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
        $weeklyChartList->filter(function ($chart) use ($registered) {
            return $chart['to'] >= $registered;
        })->each(function ($chart) {});

    }
}
