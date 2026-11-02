# ADR 0033 — Risks suggested by the other modules

- **Status:** Accepted
- **Date:** 2026-11-26

## Context

The toolkit already knows much that should feed the risk assessment: what applications process and how they are protected, where continuity falls short, what keeps failing, what expires. CLAUDE.md §9 asks for initial risks when an application is approved. The owner decided that such risks are **suggested for review, never created silently**, from four kinds of sources (application data, continuity gaps, operations, suppliers).

## Decision

- **Core registry `RiskSignals`:** each module registers, under its module key, a provider returning `RiskSignal`s for the current organization — a stable key per situation (`applications.no_mfa:<id>`), the suggested scenario and category, what it concerns (application, CMDB item, process, supplier ids), threats and vulnerabilities by catalog name, suggested likelihood/impact when the module can tell, the evidence page and a suggested owner. The Risk module reads only the registry; a disabled module registers nothing; a failing provider is reported and skipped.
- **Sources:**
  - *Applications* (documentation in force): personal / special-category / payment card data; internet exposure without MFA; certificates expiring within 30 days; components past end of support.
  - *BIA*: recovery gaps (IT cannot meet the RTO/RPO); tier 1–2 processes depending on a supplier.
  - *BCM*: assessed processes without a plan in force; plans slower than the strictest RTO.
  - *Service desk*: CMDB items with three incidents or more in 90 days.
  - *ITAM*: licences expiring within 30 days; deployed equipment out of warranty that runs CMDB items.
  - *Core*: contracts ending within 60 days that do not renew.
- **Suggestions** (`risk_suggestions`, unique per organization and key): open → *registered* (a prefilled risk: scenario, owner, what it concerns, catalog entries, scores when both are known) or *dismissed* with a reason; *no longer applies* when the situation is gone (except for a source that failed that run), reopened if it comes back. A dismissed situation is not suggested again while it lasts.
- **Refresh:** `risk:suggestions:refresh` daily at 06:30 for every organization, **Check now** on the page, and immediately (queued, after commit) when a module dispatches Core's `RiskSignalsChanged` — the Applications module does when a documentation version comes into force (initial risks, CLAUDE.md §9).
- Only risk managers (`risk.risk.manage`) see and decide suggestions.

## Alternatives considered

- **Creating risks automatically:** rejected by the owner; it would flood the register with unowned, unassessed risks.
- **The Risk module querying other modules' tables:** breaks module isolation (ADR 0001).

## Consequences

- New signals are a provider and translations in the module that knows the situation.
- Suggestion texts are stored at the last refresh while open; decided suggestions keep the text they were decided on.
