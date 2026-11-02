<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Applications\Application\Reminders\SendPortfolioDigest;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;

/**
 * Indicators of the application portfolio for the dashboards (ADR 0044).
 */
final class PortfolioMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('applications', 'applications::applications.metrics.group', [
            new Metric('applications.not_documented', 'applications::applications.metrics.not_documented', 'applications.application.view', warnAt: 1, alertAt: 5, route: 'applications.index'),
            new Metric('applications.review_overdue', 'applications::applications.metrics.review_overdue', 'applications.application.view', warnAt: 1, route: 'applications.index'),
            new Metric('applications.awaiting_approval', 'applications::applications.metrics.awaiting_approval', 'applications.application.view', route: 'applications.index'),
        ], function (): array {
            $figures = app(SendPortfolioDigest::class)->figures(CarbonImmutable::today());

            return [
                'applications.not_documented' => $figures['not_documented'],
                'applications.review_overdue' => $figures['review_overdue'],
                'applications.awaiting_approval' => $figures['awaiting_approval'],
            ];
        });
    }
}
