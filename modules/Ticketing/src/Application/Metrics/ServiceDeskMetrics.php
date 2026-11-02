<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Metrics;

use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Indicators of the service desk for the dashboards (ADR 0044).
 */
final class ServiceDeskMetrics
{
    public static function source(): MetricSource
    {
        return new MetricSource('ticketing', 'ticketing::ui.metrics.group', [
            new Metric('ticketing.open', 'ticketing::ui.metrics.open', 'ticketing.ticket.view_all', route: 'ticketing.desk.index'),
            new Metric('ticketing.unassigned', 'ticketing::ui.metrics.unassigned', 'ticketing.ticket.view_all', warnAt: 5, alertAt: 20, route: 'ticketing.desk.index', routeParameters: ['assignee' => 'unassigned']),
            new Metric('ticketing.sla_breached', 'ticketing::ui.metrics.sla_breached', 'ticketing.ticket.view_all', warnAt: 1, alertAt: 5, route: 'ticketing.desk.index', routeParameters: ['sla' => 'breached']),
        ], fn (): array => [
            'ticketing.open' => Ticket::query()->whereIn('status', TicketStatus::open())->count(),
            'ticketing.unassigned' => Ticket::query()->whereIn('status', TicketStatus::open())->whereNull('assignee_id')->count(),
            // As the desk's "SLA breached" filter: a running timer past its target.
            'ticketing.sla_breached' => Ticket::query()->whereIn('status', TicketStatus::open())
                ->whereHas('slaTimers', fn ($timers) => $timers->whereNotNull('running_since')->whereNotNull('breached_at'))->count(),
        ]);
    }
}
