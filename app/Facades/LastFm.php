<?php

declare(strict_types=1);

namespace App\Facades;

use App\Cients\LastFmClient;
use Illuminate\Support\Facades\Facade;

/**
 * @see LastFmClient
 */
final class LastFm extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return LastFmClient::class;
    }
}
