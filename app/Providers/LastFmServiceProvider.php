<?php

namespace App\Providers;

use App\Services\LastFmClient;
use Illuminate\Support\ServiceProvider;

class LastFmServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LastFmClient::class, function ($app) {
            return new LastFmClient();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
