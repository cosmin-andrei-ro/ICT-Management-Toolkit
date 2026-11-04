# ADR 0075 — Approval of service requests

- **Status:** Accepted
- **Date:** 2026-11-05

## Context

Some catalog requests should not reach IT until someone agrees: software that costs money, access to sensitive data. On 2026-11-04 the owner chose approvers named per service, with one approval being enough and a rejection closing the request.

## Decision

- **`ticketing_service_approvers`:** people per service. A service without any works as before.
- **A new status, `awaiting_approval`,** which the request starts in. It is open, so it shows in lists, but nobody can assign or work on it.
- **Agents cannot skip it.** The status workflow only allows cancelling from there; approval goes through `TicketLifecycle::approve`, reached only by `DecideServiceRequest` after the `decideRequest` policy.
- **Clocks are paused while it waits:** the time is not IT's, and a slow approver must not breach an SLA.
- **Separation of duties:** nobody decides on their own request.
- **Nothing gets stuck:** people with `ticketing.settings.manage` can decide too, for when an approver has left, or when the requester is the only approver.
- **One decision only:** a row lock keeps two approvers from both deciding. A rejection needs a comment, which the requester sees. The decision is in the status history and the audit trail.
- **Approvers see what they need,** on their own page: what was asked and by whom, never internal notes. They need no IT role.

## Alternatives considered

- **The requester's manager:** natural, but it needs a manager field kept up to date (the owner's decision).
- **Several steps in order:** more flexible, much more to build and configure.
- **Using the `pending` status:** it means "waiting for the requester", which reports and requesters read differently.

## Consequences

- Approvers are read when the request is decided, so changing them affects requests already waiting.
- Adding approvers does not affect requests already with IT.
- Requests that arrive other than through the catalog are not affected.
- A request waiting forever stays open; there is no reminder or timeout yet.
- Approvers get no notification about requests where they are themselves the requester.
