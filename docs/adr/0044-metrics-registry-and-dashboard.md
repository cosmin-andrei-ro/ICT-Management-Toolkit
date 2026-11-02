# ADR 0044 — Indicators registry and the personal dashboard

- **Status:** Accepted
- **Date:** 2026-12-06

## Context

The Reporting module shows the state of every module: dashboards by role, trends over time and a monthly management report. The owner chose:

- dashboards made of module widgets, which each person can hide and reorder;
- daily snapshots for trends;
- charts drawn on the server as SVG;
- a monthly PDF report.

Reporting must not read other modules' tables (CLAUDE.md §5.1). Most modules already compute their figures for their weekly digests.

## Decision

- **Core registry `Metrics`.** Each module registers a `MetricSource`, which has:
  - a key, used as the prefix of its indicators;
  - a group label;
  - its `Metric`s;
  - one closure that computes all of them for the current organization.

  The closure reuses the module's figures where they exist (BIA summary, continuity coverage, portfolio, risk and compliance digests). Each `Metric` has:
  - a key, a label and a unit (count, percent, minutes);
  - the **permission** needed to see it;
  - its direction (lower is better or not);
  - warning and alert thresholds, which give a status: on track, needs attention or act now;
  - optionally a link to the filtered list behind the number.
- **Visibility:** a person sees an indicator only with its permission. A source is computed only when at least one of its indicators is visible, and only once per page. `Metrics::all()` returns every value, for the snapshots of the next step.
- **Core `HomePanels`:** a list of Livewire components shown on the home page. Core does not know Reporting; Reporting adds its dashboard there.
- **Personal arrangement:** `reporting_dashboard_preferences` stores, per person and organization:
  - the indicators they hid;
  - the order of the groups.

  Only keys the person may see are accepted. Indicators added later appear by default. It is a personal setting, not a record of the organization, so it has no audit trail. **Restore defaults** deletes it.
- **Initial indicators:**
  - service desk: open, unassigned, past their SLA;
  - asset management: licences expiring, warranties ending;
  - BIA: processes never assessed, past their review, awaiting approval;
  - continuity: critical processes without a plan in force, plans not in force, overdue exercises, overdue reviews;
  - applications: not documented, past their review, awaiting approval;
  - risk: overdue actions, overdue reviews, pending acceptances, suggestions;
  - compliance: major nonconformities, overdue findings, overdue tests, requirements without a control;
  - documentation: pages past their review, pages awaiting approval.

## Alternatives considered

- **Reporting queries every module's tables:** quicker to write, but it breaks module isolation and duplicates each module's rules (what "overdue" means).
- **Widgets as Blade views per module:** freer layouts, but no common shape for snapshots, trends and the PDF report.
- **Dashboards configured by an administrator per role:** more control, but the owner preferred defaults from permissions plus personal hiding and ordering.

## Consequences

- A module adds an indicator in one place; the dashboard, the trends and the report pick it up.
- Values are computed live when the page opens, from counting queries on indexed columns; the snapshots will make trends cheap.
- A disabled module registers no source, and its group disappears.
