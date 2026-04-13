<?php

namespace App\Facades;

use App\Services\LastFmClient;
use Illuminate\Support\Facades\Facade;

/**
 * @see LastFmClient
 */
class LastFm extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return LastFmClient::class;
    }
}
