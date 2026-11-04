<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Ticketing reports',
    'menu' => 'Reports',
    'intro' => 'Incidents and service requests. Days are counted in :timezone.',
    'from' => 'From',
    'until' => 'Until',
    'invalid_period' => 'Choose a valid period of at most :days days.',
    'indicators' => 'Indicators',
    'kpi' => [
        'created' => 'Created',
        'resolved' => 'Resolved',
        'average_hours' => 'Average time to resolve (hours)',
        'median_hours' => 'Median time to resolve (hours)',
        'reopen_rate' => 'Reopened after resolution',
        'sla_compliance' => ':metric SLA met',
    ],
    'kpi_help' => 'Times to resolve are calendar hours, from creation to resolution, for tickets resolved in the period. Reopened: share of tickets resolved in the period that were reopened in the period. SLA: clocks completed in the period, cancelled ones excluded.',
    'sla_counts' => ':met met, :breached missed',
    'backlog' => 'Open now, by queue',
    'open' => 'Open',
    'oldest' => 'Oldest',
    'no_queue' => 'No queue',
    'by_category' => 'Created in the period, by category',
    'no_category' => 'No category',
    'none' => 'Nothing to show.',
    'export_title' => 'Export',
    'export_help' => 'One row per ticket created in the period (requesters are left out), plus the indicators in the XLSX file. The file is prepared in the background; it appears below for you only, for 7 days.',
    'export_xlsx' => 'Export to spreadsheet',
    'export_csv' => 'Export to CSV',
    'export' => [
        'tickets_sheet' => 'Tickets',
        'summary_sheet' => 'Summary',
        'number' => 'Number',
        'type' => 'Type',
        'title' => 'Title',
        'status' => 'Status',
        'priority' => 'Priority',
        'queue' => 'Queue',
        'category' => 'Category',
        'assignee' => 'Assignee',
        'channel' => 'Channel',
        'created' => 'Created',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'resolution_hours' => 'Hours to resolve',
        'response_sla' => 'Response SLA',
        'resolution_sla' => 'Resolution SLA',
        'indicator' => 'Indicator',
        'value' => 'Value',
        'period' => 'Period',
    ],
];
