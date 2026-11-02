# ADR 0054 — Deliverables and the new-application closure checklist

- **Status:** Accepted
- **Date:** 2026-12-16

## Context

CLAUDE.md §2.3 asks that projects make it easier to manage what they produce: a new application brings its mandatory documentation, CMDB items, risks and BIA entries. The owner chose deliverables linked to the records of other modules, and a checklist for new-application projects that blocks closure until it is complete.

## Decision

- **Deliverables** (`projects_deliverables`):
  - a phase, a title, and whether it is **required** to close;
  - optionally a link to a record of another module through Core `LinkedRecords`: the person searches and picks only records they may see, and the title is kept for readers who may not open it;
  - delivered with who and when.

  Managers add, link, mark delivered and remove them; everything is audited.
- **The application of a new-application project** (`projects_projects.application_id`, chosen through Core `Applications`, no cross-module key).
- **Checklist** (`ClosureReadiness`), computed every time from the modules and never stored:
  1. the application is chosen;
  2. its documentation has an approved version;
  3. it has its CMDB item;
  4. at least one risk concerning it has been assessed;
  5. the business processes linked in its documentation are assessed in the BIA, each with an RTO.
- **Closure is blocked** while a checklist item or a required deliverable is missing. The block applies both when the manager asks to close and again when the sponsor approves, since things may have changed in between. The reasons are listed on the project page.
- **Core contracts grow**, keeping modules apart:
  - `ApplicationSummary` gains `documented`, `configurationItemId` and `processIds`, from the approved sheet;
  - `Risks::assessedCount()` counts the assessed risks concerning something, whoever asks, because a count reveals no risk and the gate must not depend on who looks;
  - `No*` defaults keep working when a module is disabled. A disabled module leaves its checklist item undone, so an organization without it does not use new-application projects.

## Alternatives considered

- **Creating the application, risks and change automatically when the project starts:** faster, but records nobody filled in, and the owner chose the checklist.
- **Storing the checklist state:** simpler queries, but it would drift from the modules; reading live keeps one source of truth.
- **Checking risks through `concerning()` with the viewer:** the result would depend on whether the sponsor may read the register.

## Consequences

- An application project shows, at any time, what still stands between it and closure.
