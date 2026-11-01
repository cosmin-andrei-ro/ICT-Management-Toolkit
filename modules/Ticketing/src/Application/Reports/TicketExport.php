<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Reports;

use Carbon\CarbonInterface;
use Generator;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Sla\SlaMetric;

/**
 * Incidents and service requests created in a period, one row each, plus the
 * period's indicators. Requesters are left out (data minimization): the export
 * is for measuring the service desk, not for following people.
 */
final readonly class TicketExport implements ExportSource
{
    public const string TYPE = 'ticketing.tickets';

    public function __construct(
        private TicketReport $report,
        private Config $config,
    ) {}

    public function sheets(Export $export): array
    {
        $period = $this->period($export);

        return [
            new ExportSheet(__('ticketing::reports.export.tickets_sheet'), [
                __('ticketing::reports.export.number'), __('ticketing::reports.export.type'), __('ticketing::reports.export.title'),
                __('ticketing::reports.export.status'), __('ticketing::reports.export.priority'), __('ticketing::reports.export.queue'),
                __('ticketing::reports.export.category'), __('ticketing::reports.export.assignee'), __('ticketing::reports.export.channel'),
                __('ticketing::reports.export.created'), __('ticketing::reports.export.resolved'), __('ticketing::reports.export.closed'),
                __('ticketing::reports.export.resolution_hours'), __('ticketing::reports.export.response_sla'), __('ticketing::reports.export.resolution_sla'),
            ], $this->ticketRows($period)),
            new ExportSheet(__('ticketing::reports.export.summary_sheet'), [__('ticketing::reports.export.indicator'), __('ticketing::reports.export.value')], $this->summaryRows($period)),
        ];
    }

    public function filename(Export $export): string
    {
        $period = $this->period($export);

        return "tickets-{$period->firstDay}-{$period->lastDay}";
    }

    /**
     * @return Generator<list<string|int|float|null>>
     */
    private function ticketRows(ReportPeriod $period): Generator
    {
        $query = $this->report->tickets()
            ->where('created_at', '>=', $period->start)
            ->where('created_at', '<', $period->end)
            ->with(['queue' => fn ($queue) => $queue->withTrashed(), 'category' => fn ($category) => $category->withTrashed()->with('parent'), 'assignee', 'slaTimers']);

        foreach ($query->lazyById(500) as $ticket) {
            yield [
                $ticket->number,
                $ticket->type->label(),
                $ticket->title,
                $ticket->status->label(),
                $ticket->priority->label(),
                $ticket->queue?->name,
                $ticket->category?->fullName(),
                $ticket->assignee?->name,
                $ticket->channel->label(),
                $this->local($ticket->created_at),
                $this->local($ticket->resolved_at),
                $this->local($ticket->closed_at),
                $ticket->resolved_at !== null && $ticket->created_at !== null ? round($ticket->created_at->diffInSeconds($ticket->resolved_at) / 3600, 1) : null,
                $this->slaOutcome($ticket, SlaMetric::Response),
                $this->slaOutcome($ticket, SlaMetric::Resolution),
            ];
        }
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    private function summaryRows(ReportPeriod $period): array
    {
        $summary = $this->report->summary($period);
        $rows = [
            [__('ticketing::reports.export.period'), "{$period->firstDay} – {$period->lastDay}"],
            [__('ticketing::reports.kpi.created'), $summary['created']],
            [__('ticketing::reports.kpi.resolved'), $summary['resolved']],
            [__('ticketing::reports.kpi.average_hours'), $summary['average_hours']],
            [__('ticketing::reports.kpi.median_hours'), $summary['median_hours']],
            [__('ticketing::reports.kpi.reopen_rate'), $summary['reopen_rate']],
        ];

        foreach ($this->report->sla($period) as $sla) {
            $rows[] = [__('ticketing::reports.kpi.sla_compliance', ['metric' => $sla['metric']->label()]), $sla['compliance']];
        }

        return $rows;
    }

    private function slaOutcome(Ticket $ticket, SlaMetric $metric): ?string
    {
        $timer = $ticket->slaTimers->first(fn (SlaTimer $timer): bool => $timer->metric === $metric);

        return $timer?->outcome?->label();
    }

    private function local(?CarbonInterface $instant): ?string
    {
        return $instant?->copy()->setTimezone($this->timezone())->format('Y-m-d H:i');
    }

    private function period(Export $export): ReportPeriod
    {
        $from = $export->parameters['from'] ?? null;
        $until = $export->parameters['until'] ?? null;

        return ReportPeriod::between(is_string($from) ? $from : '', is_string($until) ? $until : '', $this->timezone());
    }

    private function timezone(): string
    {
        $timezone = $this->config->get('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
