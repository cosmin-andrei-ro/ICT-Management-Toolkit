# ADR 0045 — Daily indicator values and trend charts drawn on the server

- **Status:** Accepted
- **Date:** 2026-12-07

## Context

The owner wants to see how the indicators of ADR 0044 change over 30, 90 and 365 days. Modules keep their current state, not its history, and recomputing the past from their records is not possible for most figures (an overdue review on a given day). Charts must also appear in printed pages and in the PDFs rendered by Gotenberg, where JavaScript does not run, and no npm charting package should be added.

## Decision

- **Daily values:** `reporting:snapshot` runs every day at 23:30 (application time, UTC). It records, for every organization, the value of every registered indicator (`Metrics::all()`) in `reporting_metric_snapshots`:
  - columns: organization, metric key, date, value;
  - one row per indicator per day; running it again the same day replaces the values;
  - values the module could not compute are stored as null.

  Values older than `reporting.snapshots.keep_days` are removed. The default is 730 days, so a year can be compared with the previous one. The rows hold figures only, never records or personal data.
- **Charts are SVG computed in PHP** (`LineChart`):
  - the scale starts at zero and its top is rounded to 1, 2, 2.5 or 5 times a power of ten;
  - days without a value break the line rather than dropping to zero;
  - the chart is drawn in Blade with Tailwind classes, without scripts or inline styles, so it is CSP-safe and prints;
  - each day has a native tooltip;
  - a table of the values is offered next to the chart for screen readers and for copying.
- **Dashboard:** every indicator shows its last 30 days as a small line and links to its **trend page** (`/reporting/metrics/{key}`). The trend page shows:
  - the current value and its status;
  - the value at the start of the period, the lowest, the highest and the average;
  - the chart over 30, 90 or 365 days;
  - a link to the list behind the figure.

  It follows the indicator's permission: 403 without it, 404 for an unknown key.

## Alternatives considered

- **Recompute history from the modules' records:** exact for some figures, impossible for others, and it would make Reporting read every module's tables.
- **A JavaScript charting library:** richer interaction, but a new dependency, no output in PDFs and a looser CSP.
- **Snapshots only for indicators someone looked at:** less data, but holes in every trend nobody opened yet. A full day is a few dozen small rows per organization.

## Consequences

- Trends start the day the module is installed; there is no history before.
- A new indicator gets its trend from its first snapshot. An indicator that is removed keeps its rows until retention removes them.
