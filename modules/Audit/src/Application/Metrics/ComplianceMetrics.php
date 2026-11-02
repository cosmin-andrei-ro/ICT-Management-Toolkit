<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Audit\Application\Reminders\SendComplianceDigest;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;

/**
 * Indicators of compliance for the dashboards (ADR 0044).
 */
final class ComplianceMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('audit', 'audit::compliance.metrics.group', [
            new Metric('audit.major_open', 'audit::compliance.metrics.major_open', 'audit.compliance.view', warnAt: 1, alertAt: 1, route: 'audit.findings.index', routeParameters: ['severity' => 'major_nonconformity']),
            new Metric('audit.findings_overdue', 'audit::compliance.metrics.findings_overdue', 'audit.compliance.view', warnAt: 1, alertAt: 5, route: 'audit.findings.index', routeParameters: ['overdue' => 1]),
            new Metric('audit.tests_overdue', 'audit::compliance.metrics.tests_overdue', 'audit.compliance.view', warnAt: 1, route: 'audit.controls.index', routeParameters: ['testOverdue' => 1]),
            new Metric('audit.requirements_not_covered', 'audit::compliance.metrics.requirements_not_covered', 'audit.compliance.view', warnAt: 1, alertAt: 10, route: 'audit.index'),
        ], function (): array {
            $figures = app(SendComplianceDigest::class)->figures(CarbonImmutable::today());

            return [
                'audit.major_open' => $figures['major_open'],
                'audit.findings_overdue' => $figures['findings_overdue'],
                'audit.tests_overdue' => $figures['tests_overdue'],
                'audit.requirements_not_covered' => $figures['requirements_not_covered'],
            ];
        });
    }
}
