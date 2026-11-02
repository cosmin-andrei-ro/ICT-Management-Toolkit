<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Bia\Application\Reviews\BiaSummary;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;

/**
 * Indicators of the business impact analysis for the dashboards (ADR 0044).
 */
final class ImpactMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('bia', 'bia::processes.metrics.group', [
            new Metric('bia.never_assessed', 'bia::processes.metrics.never_assessed', 'bia.process.view', warnAt: 1, alertAt: 5, route: 'bia.processes.index'),
            new Metric('bia.review_overdue', 'bia::processes.metrics.review_overdue', 'bia.process.view', warnAt: 1, route: 'bia.processes.index'),
            new Metric('bia.awaiting_approval', 'bia::processes.metrics.awaiting_approval', 'bia.process.view', route: 'bia.processes.index'),
        ], function (): array {
            $figures = app(BiaSummary::class)->figures(CarbonImmutable::today());

            return ['bia.never_assessed' => $figures['never_assessed'], 'bia.review_overdue' => $figures['overdue'], 'bia.awaiting_approval' => $figures['awaiting_approval']];
        });
    }
}
