<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricStatus;

it('rates values where lower is better against its thresholds', function (?int $value, MetricStatus $status): void {
    $metric = new Metric('demo.overdue', 'Overdue', 'demo.view', warnAt: 1, alertAt: 5);

    expect($metric->status($value))->toBe($status);
})->with([
    [0, MetricStatus::Good],
    [1, MetricStatus::Warning],
    [4, MetricStatus::Warning],
    [5, MetricStatus::Alert],
    [null, MetricStatus::Neutral],
]);

it('rates values where higher is better against its thresholds', function (float $value, MetricStatus $status): void {
    $metric = new Metric('demo.coverage', 'Coverage', 'demo.view', lowerIsBetter: false, warnAt: 90, alertAt: 75);

    expect($metric->status($value))->toBe($status);
})->with([
    [95.0, MetricStatus::Good],
    [90.0, MetricStatus::Warning],
    [80.0, MetricStatus::Warning],
    [75.0, MetricStatus::Alert],
]);

it('stays neutral without thresholds', function (): void {
    expect((new Metric('demo.count', 'Count', 'demo.view'))->status(42))->toBe(MetricStatus::Neutral);
});
