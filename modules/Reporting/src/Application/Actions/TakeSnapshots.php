<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Domain\Models\MetricSnapshot;

/**
 * Records today's value of every indicator of every organization; running it
 * again the same day replaces the values. Values older than the retention
 * period are removed.
 */
final readonly class TakeSnapshots
{
    public function __construct(private Metrics $metrics, private OrganizationContext $context) {}

    /**
     * @return int Values recorded.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $recorded = 0;

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $recorded += $this->context->runAs($organization->id, function () use ($organization, $today): int {
                $rows = [];
                $now = now();

                foreach ($this->metrics->all() as $key => $value) {
                    $rows[] = [
                        'id' => (string) Str::uuid7(),
                        'organization_id' => $organization->id,
                        'metric_key' => $key,
                        'taken_on' => $today->toDateString(),
                        'value' => $value,
                        'created_at' => $now,
                    ];
                }

                MetricSnapshot::query()->upsert($rows, ['organization_id', 'metric_key', 'taken_on'], ['value', 'created_at']);

                return count($rows);
            });
        }

        $keepDays = config('reporting.snapshots.keep_days');
        MetricSnapshot::query()->withoutGlobalScopes()
            ->where('taken_on', '<', $today->subDays(is_int($keepDays) ? $keepDays : 730)->toDateString())
            ->delete();

        return $recorded;
    }
}
