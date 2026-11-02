<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Reporting\Application\Charts\LineChart;

it('rounds the top of the scale to a readable number', function (float $max, float $top): void {
    expect(LineChart::niceMax($max))->toBe($top);
})->with([
    [0.0, 1.0],
    [1.0, 1.0],
    [3.0, 5.0],
    [7.0, 10.0],
    [12.0, 20.0],
    [21.0, 25.0],
    [480.0, 500.0],
    [97.5, 100.0],
]);

it('draws the values across the width, the largest near the top', function (): void {
    $chart = LineChart::make(['2026-12-01' => 0, '2026-12-02' => 5, '2026-12-03' => 10], 100, 50);

    expect($chart->paths)->toBe(['M0 50 L50 25 L100 0'])
        ->and(array_column($chart->ticks, 'value'))->toBe([0.0, 5.0, 10.0]);
});

it('breaks the line on days without a value instead of drawing zero', function (): void {
    $chart = LineChart::make(['2026-12-01' => 2, '2026-12-02' => 4, '2026-12-03' => null, '2026-12-04' => 4], 30, 10);

    expect($chart->paths)->toHaveCount(2)
        ->and($chart->points)->toHaveCount(3)
        ->and($chart->points[2]['x'])->toBe(30.0);
});

it('draws nothing without values', function (): void {
    $chart = LineChart::make(['2026-12-01' => null, '2026-12-02' => null], 100, 50);

    expect($chart->paths)->toBe([])->and($chart->points)->toBe([]);
});
