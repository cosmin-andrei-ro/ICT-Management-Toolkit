<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Metrics;

use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Core\Domain\Metrics\MetricUnit;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;

/**
 * Indicators of the data centre for the dashboards (ADR 0044, 0051).
 */
final class DcimMetrics
{
    /** An IPv4 subnet this full needs a plan. */
    public const int SUBNET_FULL_PERCENT = 90;

    public static function source(): MetricSource
    {
        $view = 'dcim.infrastructure.view';

        return new MetricSource('dcim', 'dcim::reports.metrics.group', [
            new Metric('dcim.rack_space_used', 'dcim::reports.metrics.rack_space_used', $view, MetricUnit::Percent, warnAt: 80, alertAt: 90, route: 'dcim.racks.index'),
            new Metric('dcim.power_over', 'dcim::reports.metrics.power_over', $view, warnAt: 1, alertAt: 1, route: 'dcim.power.index'),
            new Metric('dcim.power_high', 'dcim::reports.metrics.power_high', $view, warnAt: 1, route: 'dcim.power.index'),
            new Metric('dcim.single_fed', 'dcim::reports.metrics.single_fed', $view, warnAt: 1, route: 'dcim.power.index'),
            new Metric('dcim.subnets_full', 'dcim::reports.metrics.subnets_full', $view, warnAt: 1, route: 'dcim.ipam.index'),
        ], self::values(...));
    }

    /**
     * @return array<string, int|float|null>
     */
    public static function values(): array
    {
        $racks = Rack::query()->with('mounts')->get();
        $units = $racks->sum(fn (Rack $rack): int => $rack->height_u);
        $grid = PowerGrid::read();
        $warn = config()->integer('dcim.power.warn_percent', 80);
        $high = 0;
        $over = 0;

        foreach (PowerSource::query()->whereNotNull('capacity_watts')->get() as $source) {
            $load = $grid->loadOf($source);
            $peak = PowerGrid::percent(max($load['normal'], $load['worst']), $source->capacity_watts) ?? 0;

            if ($peak > 100) {
                $over++;
            } elseif ($peak >= $warn) {
                $high++;
            }
        }

        $full = 0;

        foreach (Subnet::query()->selectRaw('dcim_subnets.*, (SELECT count(*) FROM dcim_ip_addresses a WHERE a.organization_id = dcim_subnets.organization_id AND a.address << dcim_subnets.network) AS used')->whereRaw('family(network) = 4')->get() as $subnet) {
            $usable = $subnet->parsed()->usable();
            $used = $subnet->getAttribute('used');

            if ($usable !== null && $usable > 0 && is_numeric($used) && (int) $used / $usable * 100 >= self::SUBNET_FULL_PERCENT) {
                $full++;
            }
        }

        return [
            'dcim.rack_space_used' => $units > 0 ? round($racks->sum(fn (Rack $rack): int => $rack->usedUnits()) / $units * 100, 1) : null,
            'dcim.power_over' => $over,
            'dcim.power_high' => $high,
            'dcim.single_fed' => count($grid->singleFed()),
            'dcim.subnets_full' => $full,
        ];
    }
}
