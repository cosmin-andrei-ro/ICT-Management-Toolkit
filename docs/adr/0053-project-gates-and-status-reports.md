# ADR 0053 — Stage gates and RAG status reports

- **Status:** Accepted
- **Date:** 2026-12-15

## Context

The owner chose stage gates approved by the sponsor, and periodic green/amber/red status reports with a portfolio for management (ADR 0052 for phases and tasks).

## Decision

- **Gate requests** (`projects_gate_requests`): the project manager asks to move from the phase under way to the next one. From closure, the request is to close the project (`to_phase_id` null).
  - **Rules:**
    - only for active projects;
    - a sponsor must be named;
    - one request waits at a time (partial unique index);
    - a manager who is also the sponsor cannot ask, because nobody approves their own request.
  - **The sponsor**, and only the sponsor, **approves**. The phase is marked done and the next one starts, or the project is closed (it then becomes read-only).
  - **Sending a request back** needs a reason.

  The sponsor is emailed when a request arrives and the manager when it is decided (queued, after commit, `Auto-Submitted`). Sponsors also find pending requests under **My tasks**. Requests and decisions are kept and audited.
- **Status reports** (`projects_status_reports`): the manager rates the project green, amber or red overall and on schedule, budget and scope, and writes what was done, what comes next, and the issues and risks. One report per day: writing again replaces it.
- **Reminders:** `projects:report-reminders` runs on Mondays at 08:30 and emails each manager their active projects without a report in the last `PROJECTS_REPORT_EVERY_DAYS` days (14). The project page also says when a report is due.
- **Portfolio** (`/projects/portfolio`, for `projects.project.view` and `projects.project.manage`): open projects with phase, latest RAG and its date (marked when stale), waiting gates, manager, sponsor, planned end, and counts by RAG.
- RAG is always shown with its word, not colour alone.

## Alternatives considered

- **Several approvers per gate (a steering committee):** heavier for most IT projects. A single named sponsor matches the owner's answer, and a committee can be added later.
- **Holders of `projects.project.manage` deciding on gates:** convenient, but it would let project managers approve one another's projects without the business owner.
- **Reports on a fixed weekday:** projects move at different paces; a maximum age is simpler and fair.

## Consequences

- Closing through the last gate is where the next step adds the closure checklist: deliverables, and the new-application items.
