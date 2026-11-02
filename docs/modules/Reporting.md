# Reporting module

Dashboards, trends and management reports built from the indicators of every module. Code: `modules/Reporting/`. Decisions: [ADR 0044](../adr/0044-metrics-registry-and-dashboard.md).

## Concepts

- **Indicator** (Core `Metric`, registered by each module through a `MetricSource` in Core's `Metrics`): key (`ticketing.open`), label, unit, the permission needed to see it, direction, warning and alert thresholds, a link to the filtered list.
- **Dashboard preference** (`reporting_dashboard_preferences`): per person and organization, the hidden indicators and the order of the groups.

## Screens

- Home page: the indicators the person may see, grouped by module, coloured by status, each number linking to the list behind it. **Customise** hides or shows indicators and moves groups up or down; **Restore defaults** forgets the arrangement.

## For other modules

Register a source in the module's service provider:

```php
$this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(MyMetrics::source()));
```

Every indicator key starts with the source key followed by a dot. The closure returns the values of the current organization, keyed by indicator.

## Permissions

None of its own yet: each indicator follows the permission of its module.

## Plan

1. Indicators registry, initial indicators of each module, personal dashboard on the home page — **done**.
2. Daily snapshots, SVG trend charts over 30, 90 and 365 days, a page per indicator.
3. Management dashboard and the monthly management report as PDF, sent to subscribers.
