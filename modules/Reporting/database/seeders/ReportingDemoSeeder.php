<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Metrics\MetricUnit;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Application\Actions\ManageReportSubscriptions;
use Modules\Reporting\Application\Actions\TakeSnapshots;
use Modules\Reporting\Domain\Models\MetricSnapshot;

/**
 * Runs after every other module: today's snapshot of the indicators, from
 * the demo data, and 89 days before it - modules only know their current
 * state, so the earlier values are drawn around today's (the same every
 * seed), for trends that have something to show. The administrator
 * receives the monthly management report.
 */
final class ReportingDemoSeeder extends Seeder
{
    private const int DAYS = 90;

    public function run(DemoCompany $company, OrganizationContext $context, TakeSnapshots $snapshots, Metrics $metrics, ManageReportSubscriptions $subscriptions): void
    {
        $today = DemoClock::today();
        $snapshots->handle($today);

        $context->runAs($company->organization, function () use ($company, $today, $metrics, $subscriptions): void {
            $rows = [];

            foreach (MetricSnapshot::query()->where('taken_on', $today->toDateString())->get() as $snapshot) {
                $metric = $metrics->metric($snapshot->metric_key);
                $current = $snapshot->value !== null ? (float) $snapshot->value : null;

                for ($daysAgo = 1; $daysAgo < self::DAYS; $daysAgo++) {
                    $rows[] = [
                        'id' => (string) Str::uuid7(),
                        'organization_id' => $company->organization->id,
                        'metric_key' => $snapshot->metric_key,
                        'taken_on' => $today->subDays($daysAgo)->toDateString(),
                        'value' => $current !== null ? self::earlier($snapshot->metric_key, $current, $daysAgo, $metric !== null ? $metric->unit : MetricUnit::Count) : null,
                        'created_at' => $today->subDays($daysAgo)->setTime(23, 55),
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                MetricSnapshot::query()->insert($chunk);
            }

            // Only the administrator sees the management overview among the default roles.
            $subscriptions->subscribe($company->user('admin'), $company->user('admin')->id);
        });
    }

    /**
     * A value some days back. Counts change in one step, on a day fixed per
     * indicator (small counts do not flicker day to day); large counts,
     * percentages and durations drift, with day-to-day noise.
     */
    private static function earlier(string $key, float $current, int $daysAgo, MetricUnit $unit): float
    {
        $seed = crc32($key);
        $direction = $seed % 2 === 0 ? 1 : -1;
        $noise = ((crc32($key.'|'.$daysAgo) % 1000) / 1000 - 0.5) * 0.2;

        if ($unit === MetricUnit::Count) {
            $changedDaysAgo = 10 + $seed % 70;
            $step = $daysAgo >= $changedDaysAgo ? $direction * max(1.0, round(abs($current) * 0.3)) : 0.0;
            $jitter = abs($current) >= 10 ? round($noise * abs($current)) : 0.0;

            return max(0.0, $current + $step + $jitter);
        }

        $scale = max(abs($current), $unit === MetricUnit::Percent ? 10.0 : 3.0);
        $value = $current + $direction * 0.35 * $scale * ($daysAgo / self::DAYS) + $noise * $scale;

        return $unit === MetricUnit::Percent ? round(min(100.0, max(0.0, $value)), 1) : max(0.0, round($value));
    }
}
