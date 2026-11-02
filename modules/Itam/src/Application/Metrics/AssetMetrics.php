<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;

/**
 * Indicators of assets and licences for the dashboards (ADR 0044).
 */
final class AssetMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('itam', 'itam::assets.metrics.group', [
            new Metric('itam.licenses_expiring', 'itam::assets.metrics.licenses_expiring', 'itam.license.view', warnAt: 1, route: 'itam.licenses.index'),
            new Metric('itam.warranties_ending', 'itam::assets.metrics.warranties_ending', 'itam.asset.view', route: 'itam.assets.index'),
        ], function (): array {
            $today = CarbonImmutable::today();

            return [
                'itam.licenses_expiring' => License::query()->whereBetween('expires_on', [$today->toDateString(), $today->addDays(60)->toDateString()])->count(),
                'itam.warranties_ending' => Asset::query()->whereBetween('warranty_ends_on', [$today->toDateString(), $today->addDays(30)->toDateString()])->count(),
            ];
        });
    }
}
