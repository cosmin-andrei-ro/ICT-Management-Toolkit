<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Bcm\Application\Coverage\ContinuityCoverage;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;

/**
 * Indicators of continuity and recovery plans for the dashboards (ADR 0044).
 */
final class ContinuityMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('bcm', 'bcm::plans.metrics.group', [
            new Metric('bcm.uncovered', 'bcm::plans.metrics.uncovered', 'bcm.plan.view', warnAt: 1, alertAt: 3, route: 'bcm.coverage'),
            new Metric('bcm.not_in_force', 'bcm::plans.metrics.not_in_force', 'bcm.plan.view', warnAt: 1, route: 'bcm.plans.index'),
            new Metric('bcm.exercise_overdue', 'bcm::plans.metrics.exercise_overdue', 'bcm.plan.view', warnAt: 1, route: 'bcm.coverage'),
            new Metric('bcm.review_overdue', 'bcm::plans.metrics.review_overdue', 'bcm.plan.view', warnAt: 1, route: 'bcm.coverage'),
        ], function (): array {
            $figures = app(ContinuityCoverage::class)->figures(CarbonImmutable::today());

            return [
                'bcm.uncovered' => $figures['uncovered'],
                'bcm.not_in_force' => $figures['not_in_force'],
                'bcm.exercise_overdue' => $figures['exercise_overdue'],
                'bcm.review_overdue' => $figures['review_overdue'],
            ];
        });
    }
}
