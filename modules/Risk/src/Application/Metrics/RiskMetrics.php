<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Risk\Application\Reminders\SendRiskDigest;

/**
 * Indicators of the risk register for the dashboards (ADR 0044).
 */
final class RiskMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('risk', 'risk::risks.metrics.group', [
            new Metric('risk.overdue_actions', 'risk::risks.metrics.overdue_actions', 'risk.risk.view', warnAt: 1, alertAt: 5, route: 'risk.index'),
            new Metric('risk.reviews_overdue', 'risk::risks.metrics.reviews_overdue', 'risk.risk.view', warnAt: 1, route: 'risk.index'),
            new Metric('risk.pending_acceptances', 'risk::risks.metrics.pending_acceptances', 'risk.risk.view', warnAt: 1, route: 'risk.index'),
            new Metric('risk.suggestions', 'risk::risks.metrics.suggestions', 'risk.risk.manage', route: 'risk.risks.suggestions'),
        ], function (): array {
            $figures = app(SendRiskDigest::class)->figures(CarbonImmutable::today());

            return [
                'risk.overdue_actions' => $figures['overdue_actions'],
                'risk.reviews_overdue' => $figures['reviews_overdue'],
                'risk.pending_acceptances' => $figures['pending_acceptances'],
                'risk.suggestions' => $figures['suggestions'],
            ];
        });
    }
}
