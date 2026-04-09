<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Facades\LastFm;

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

            if( !$user ) {
                $this->error('No se especificó un usuario');
                return;
            }


            $this->info("Sincronizando Last.fm para el usuario: {$user}");

            // Prueba de conexión con artista
            // $response = LastFm::getArtistInfo('babymonster');
            
            // $this->line(json_encode($response, JSON_PRETTY_PRINT));
        }
    }
