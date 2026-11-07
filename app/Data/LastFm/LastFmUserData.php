<?php

declare(strict_types=1);

namespace App\Data\LastFm;

use App\Data\Data;
use Illuminate\Support\Collection;

final readonly class LastFmUserData extends Data
{
    public function __construct(
        public string $name,
        public int $registeredUnixtime,
        public int $playcount = 0,
    ) {}

    public static function fromApi(array|Collection $data): self
    {
        return new self(
            name: (string) data_get($data, 'name', ''),
            registeredUnixtime: (int) data_get($data, 'registered.unixtime', 0),
            playcount: (int) data_get($data, 'playcount', 0),
        );
    }
}
