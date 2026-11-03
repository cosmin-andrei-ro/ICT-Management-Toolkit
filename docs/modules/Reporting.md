# Reporting module

Dashboards, trends and management reports built from the indicators of every module. Code: `modules/Reporting/`. Decisions: [ADR 0044](../adr/0044-metrics-registry-and-dashboard.md), [ADR 0045](../adr/0045-daily-snapshots-and-svg-trends.md), [ADR 0046](../adr/0046-management-overview-and-monthly-report.md).

## Concepts

- **Indicator** (Core `Metric`, registered by each module through a `MetricSource` in Core's `Metrics`): key (`ticketing.open`), label, unit, the permission needed to see it, direction, warning and alert thresholds, a link to the filtered list.
- **Dashboard preference** (`reporting_dashboard_preferences`): per person and organization, the hidden indicators and the order of the groups.
- **Report subscription** (`reporting_report_subscriptions`, audited): a person who receives the monthly report.
- **Snapshot** (`reporting_metric_snapshots`): the value of an indicator on a day, per organization; unique per indicator and day. Kept `reporting.snapshots.keep_days` days (default 730, `REPORTING_SNAPSHOT_KEEP_DAYS`).

## Screens

- Home page: the indicators the person may see, grouped by module, coloured by status, each number linking to the list behind it. **Customise** hides or shows indicators and moves groups up or down; **Restore defaults** forgets the arrangement. Each indicator shows its last 30 days as a small line.
- `/reporting/metrics/{key}[?days=30|90|365]`: one indicator - current value and status, start of the period, lowest, highest, average, the chart (SVG drawn on the server) and its values as a table, a link to the list behind it.
- `/reporting/management` (**Management** in the menu): every indicator of every module - now, status, change in 30 days, last 90 days; the monthly report to open; who receives it by email (administrators).
- `/reporting/report[?month=Y-m]` and `/pdf`: the monthly report - each indicator at the end of the month and the month before, its status, its last twelve months; this month and the eleven before.

## Scheduled tasks

- `reporting:snapshot` (daily 23:30): records today's value of every indicator of every organization and removes values past the retention.
- `reporting:monthly-report` (1st of the month, 07:00): emails the report of the month that ended to the subscribers who may still see it, with the PDF attached where the PDF service runs (`REPORTING_ATTACH_PDF`).

## For other modules

Register a source in the module's service provider:

```php
$this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(MyMetrics::source()));
```

Every indicator key starts with the source key followed by a dot. The closure returns the values of the current organization, keyed by indicator.

## Permissions

Each indicator on the personal dashboard follows the permission of its module.

| Permission | Default roles | Purpose |
|---|---|---|
| `reporting.management.view` | administrators | Management overview and monthly report: every module's figures, not their records |
| `reporting.subscriptions.manage` | administrators | Choose who receives the monthly report |

## Plan

1. Indicators registry, initial indicators of each module, personal dashboard on the home page — **done**.
2. Daily snapshots, SVG trend charts over 30, 90 and 365 days, a page per indicator — **done**.
3. Management overview and the monthly management report as PDF, sent to subscribers — **done**.

## API (ADR 0065)

`metrics:read`, read-only.

- `GET /api/v1/metrics`: the indicators the user's permissions show (`Metrics::visibleTo`), with value, unit and status.
- `/metrics/{key}/history?days=1..730`: daily snapshots. Answers 404 for an indicator the user may not see.
