# ADR 0055 — Project budget, indicators, portfolio workbook and status report

- **Status:** Accepted
- **Date:** 2026-12-17

## Context

The owner chose to track each project's approved budget against actual costs by category, without timesheets. Projects should also appear on the dashboards and in the monthly report, like the other modules (ADR 0044–0046).

## Decision

- **Budget:** `projects_projects.budget_amount` and `currency`, one currency per project (default `DEFAULT_CURRENCY`). Once costs are recorded, the currency cannot change.
- **Costs** (`projects_costs`):
  - a category: external labour, licences, hardware, services or other;
  - a description, an amount, and a date;
  - optionally a supplier of the organization and an invoice number.

  The amount is in the project's currency, never zero, and negative for a credit note. Costs are audited and recorded by the project's manager (or `projects.project.manage`). Anyone who may see the project sees them.
- **`BudgetFigures`:** budget, spent, remaining, share used, spending by category, over budget.
- **Indicators** (source `projects`, permission `projects.project.view`):

  | Indicator | Thresholds |
  |---|---|
  | open projects whose latest report is red | red at 1 |
  | open projects over budget | amber at 1 |
  | overdue tasks of open projects | amber at 1, red at 20 |
  | gates waiting for a sponsor | none |
- **Workbook** (`projects.portfolio`, from the Portfolio page, queued and audited) with four sheets:
  - projects: phase, latest RAG, budget and spending, task counts;
  - costs;
  - deliverables;
  - status reports.
- **Status report sheet** (`/projects/{id}/report`, `/pdf` throttled, for anyone who may see the project):
  - phases with dates;
  - the latest RAG report;
  - the budget by category;
  - deliverables, the checklist and what blocks closure;
  - overdue tasks and the sponsor's decisions.

  It is printed through `x-core::printable`; the PDF goes through `PdfDownload` and is recorded as an export.

## Alternatives considered

- **Timesheets (hours × rate):** the owner declined; external labour is entered as invoiced.
- **Costs in several currencies with conversion:** exchange rates add a dependency and ambiguity. One currency per project keeps the figures exact.
- **Budget lines per category (planned vs actual per category):** more planning work. The approved total, with spending shown by category, answers "are we within budget".

## Consequences

- The Projects module is complete, and with it every module of CLAUDE.md §3.
