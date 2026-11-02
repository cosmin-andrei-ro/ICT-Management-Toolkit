<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Metrics\MetricStatus;

/**
 * The management report of a month (ADR 0046): every indicator as it stood
 * at the end of the month, against the end of the month before, with its
 * last twelve months. Built from the daily values only, so a past month
 * always reads the same.
 */
final readonly class MonthlyReport
{
    public function __construct(private Metrics $metrics, private Trends $trends) {}

    /**
     * The months a report can be made for: this one (so far) and the eleven before.
     *
     * @return list<CarbonImmutable> First day of each month, newest first.
     */
    public static function months(?CarbonImmutable $today = null): array
    {
        $first = ($today ?? CarbonImmutable::today())->startOfMonth();

        return array_map(fn (int $back): CarbonImmutable => $first->subMonthsNoOverflow($back), range(0, 11));
    }

    /**
     * The month a "Y-m" value names, if a report can be made for it.
     */
    public static function month(string $value, ?CarbonImmutable $today = null): ?CarbonImmutable
    {
        foreach (self::months($today) as $month) {
            if ($month->format('Y-m') === $value) {
                return $month;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     month: CarbonImmutable,
     *     end: CarbonImmutable,
     *     statuses: array<string, int>,
     *     groups: list<array{label: string, rows: list<array{metric: Metric, closing: ?float, previous: ?float, status: MetricStatus, year: array<string, float|null>}>}>
     * }
     */
    public function build(CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $month = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay()->min($today);
        $previousEnd = $month->subDay();

        $keys = [];

        foreach ($this->metrics->sources() as $source) {
            foreach ($source->metrics as $metric) {
                $keys[] = $metric->key;
            }
        }

        $current = $this->trends->series($keys, $end->day, $end);
        $previous = $this->trends->series($keys, $previousEnd->day, $previousEnd);
        $year = $this->trends->series($keys, 365, $end);

        $statuses = array_fill_keys(array_map(fn (MetricStatus $status): string => $status->value, MetricStatus::cases()), 0);
        $groups = [];

        foreach ($this->metrics->sources() as $source) {
            $rows = [];

            foreach ($source->metrics as $metric) {
                $closing = self::last($current[$metric->key]);
                $status = $metric->status($closing);
                $statuses[$status->value]++;
                $rows[] = [
                    'metric' => $metric,
                    'closing' => $closing,
                    'previous' => self::last($previous[$metric->key]),
                    'status' => $status,
                    'year' => $year[$metric->key],
                ];
            }

            $groups[] = ['label' => $source->label, 'rows' => $rows];
        }

        return ['month' => $month, 'end' => $end, 'statuses' => $statuses, 'groups' => $groups];
    }

    /**
     * @param  array<string, float|null>  $series
     */
    private static function last(array $series): ?float
    {
        foreach (array_reverse($series) as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
