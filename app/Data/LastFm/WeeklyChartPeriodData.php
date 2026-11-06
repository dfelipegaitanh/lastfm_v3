<?php

declare(strict_types=1);

namespace App\Data\LastFm;

final readonly class WeeklyChartPeriodData
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
