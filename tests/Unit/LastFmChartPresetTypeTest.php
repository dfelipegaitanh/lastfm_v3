<?php

declare(strict_types=1);

use App\Enums\LastFmChartPresetType;

test('LastFmChartPresetType has expected cases and values', function (): void {
    expect(LastFmChartPresetType::Top->value)->toBe('top')
        ->and(LastFmChartPresetType::Viral->value)->toBe('viral')
        ->and(array_column(LastFmChartPresetType::cases(), 'value'))->toBe(['top', 'viral']);
});
