<?php

declare(strict_types=1);

namespace App\Data\LastFm;

use App\Data\Data;

final readonly class WeeklyChartPeriodData extends Data
{
    public function __construct(
        public int $from,
        public int $to,
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            from: (int) ($data['from'] ?? 0),
            to: (int) ($data['to'] ?? 0),
        );
    }
}
