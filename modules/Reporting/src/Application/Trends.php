<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application;

use Carbon\CarbonImmutable;
use Modules\Reporting\Domain\Models\MetricSnapshot;

/**
 * Daily values of indicators of the current organization, one entry per day
 * of the period, null for days without a value.
 */
final readonly class Trends
{
    /** Periods offered, in days. */
    public const array PERIODS = [30, 90, 365];

    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, float|null>> Value by date (Y-m-d) by metric key, oldest first.
     */
    public function series(array $keys, int $days, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $today->subDays($days - 1);
        $empty = [];

        for ($day = $from; $day <= $today; $day = $day->addDay()) {
            $empty[$day->toDateString()] = null;
        }

        $series = [];

        foreach ($keys as $key) {
            $series[$key] = $empty;
        }

        if ($keys === []) {
            return $series;
        }

        $snapshots = MetricSnapshot::query()->whereIn('metric_key', $keys)
            ->whereBetween('taken_on', [$from->toDateString(), $today->toDateString()])
            ->get(['metric_key', 'taken_on', 'value']);

        foreach ($snapshots as $snapshot) {
            $series[$snapshot->metric_key][$snapshot->taken_on->toDateString()] = $snapshot->value === null ? null : (float) $snapshot->value;
        }

        return $series;
    }
}
