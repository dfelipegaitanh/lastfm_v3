<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\LastFmClient;
use Illuminate\Support\ServiceProvider;

final class LastFmServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LastFmClient::class, function ($app): \App\Services\LastFmClient {
            return new LastFmClient;
        });
    }
}
