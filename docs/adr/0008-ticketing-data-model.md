# ADR 0008 — Ticketing data model

- **Status:** Accepted
- **Date:** 2026-11-02

## Context

ITIL 4 distinguishes incidents, service requests, problems and changes. They share most of their lifecycle (queue, assignee, comments, attachments, SLA, reporting) but differ in workflow and in a few fields.

## Decision

- **One `ticketing_tickets` table with a `type` column.** Fields specific to problems and changes will live in 1:1 tables (stages 4–5). This gives unified lists, search, SLA timers and reporting without UNIONs. The `type` CHECK currently allows `incident` and `service_request`. Later stages widen it together with their workflows.
- **Status changes only through actions** (`Application\Actions`), all going through `TicketLifecycle`:
  - the transition is validated by `TicketWorkflow`;
  - it is recorded in `ticketing_status_transitions`, for time-in-status reporting;
  - it is audited, and it dispatches events after commit.
  - The model has no fillable attributes.
- **Database invariants**:
  - CHECK constraints for every enum;
  - work in progress requires an assignee;
  - resolved and closed tickets require a resolution;
  - closed tickets require `closed_at`;
  - composite foreign keys keep queues and categories inside the ticket's organization.
- **Numbers** (`INC-000042`, `REQ-000007`) are per organization and type. A single atomic `INSERT … ON CONFLICT … RETURNING` hands them out, so concurrent requests never collide. A rolled-back transaction may leave a gap, which is acceptable. URLs use the UUID, never the number (no enumeration).
- **Priority** is derived from impact × urgency, using the ITIL default matrix. Organizations can override single cells in `ticketing_priority_matrix`. Priority is never set by hand.
- **Search** uses a generated `tsvector` column with a GIN index. The number goes through the `simple` configuration (kept intact); title and description go through `english` (stemmed).
- **Visibility** (owner's decision):
  - Requesters see their own tickets and those they watch.
  - Agents (`ticketing.ticket.view_all`) see every ticket of the organization.
  - Internal comments and attachments are visible to agents only.
- **Resolved tickets close automatically** after `TICKETING_AUTO_CLOSE_AFTER_DAYS` (5 by default). Until then the requester can reopen them. A requester's reply to a pending ticket brings it back into work.
- **Assignment** requires the `ticketing.ticket.work` permission in the ticket's organization and, when the ticket has a queue, membership of the queue's team.

## Consequences

- New ticket types need a migration (CHECK), a workflow, and their own detail table.
- Reporting can rely on `ticketing_status_transitions`, without parsing the audit log.
