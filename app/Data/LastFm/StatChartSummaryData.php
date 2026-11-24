<?php

declare(strict_types=1);

namespace App\Data\LastFm;

use App\Data\Data;

final readonly class StatChartSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $user,
        public string $from,
        public string $to,
        public bool $synced,
        public string $preset_type,
        public int $play_count_limit,
        public int $total_charts_synced,
        public int $total_playcount,
    ) {}
}
