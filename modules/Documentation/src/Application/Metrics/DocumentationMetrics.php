<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Metrics;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Documentation\Domain\Models\Page;

/**
 * Indicators of the documentation for the dashboards (ADR 0044): pages of every space, counted only.
 */
final class DocumentationMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('documentation', 'documentation::docs.metrics.group', [
            new Metric('documentation.review_overdue', 'documentation::docs.metrics.review_overdue', 'documentation.space.manage', warnAt: 1, alertAt: 10, route: 'documentation.index'),
            new Metric('documentation.awaiting_approval', 'documentation::docs.metrics.awaiting_approval', 'documentation.space.manage', route: 'documentation.index'),
        ], fn (): array => [
            'documentation.review_overdue' => Page::query()->whereNotNull('published_version')->where('review_on', '<', CarbonImmutable::today()->toDateString())->count(),
            'documentation.awaiting_approval' => Page::query()->whereNotNull('pending_version')->count(),
        ]);
    }
}
