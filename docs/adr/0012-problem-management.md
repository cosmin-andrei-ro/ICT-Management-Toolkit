# ADR 0012 — Problem management

- **Status:** Accepted
- **Date:** 2026-11-05

## Context

Problem management finds and removes the causes of incidents. Agents need to group incidents under a problem, document the root cause and a workaround (a "known error"), find known errors quickly, and close the incidents once the cause is fixed.

## Decision

- **Problems are tickets of type `problem`** (`PRB-` numbers), as planned in ADR 0008. They reuse queues, assignment, comments, attachments, status history and audit. They have **no SLA clocks**: they are investigated, not answered to a requester.
- **Analysis lives in `ticketing_problem_details` (1:1):** root cause, workaround and `known_error_at`. A known error must have a workaround, enforced by a CHECK.
- **Incidents point to their problem** (`ticketing_tickets.problem_id`). The link is enforced in the database:
  - a composite FK keeps it within the organization;
  - a CHECK allows it on incidents only;
  - a trigger checks that the target is a problem (a CHECK cannot read another row).
- **Who** (owner's decision): every agent may manage problems, through `ticketing.problem.manage`. Being a separate permission, it can be restricted to dedicated problem managers without code.
- **Known error database:** `/desk/known-errors` lists known errors that are not closed, ordered by the number of linked incidents, and searchable in the ticket text, root cause and workaround. Linking an incident to a known error adds the workaround to it as an internal note.
- **Resolving a problem** can resolve its open linked incidents in the same step (owner's decision: offered and checked by default). Each incident is resolved through the normal action, so requesters are notified and can reopen if the fix does not work for them.
- **Problems stay internal:** they never appear in the portal, and requesters cannot raise them (the portal and the API only accept incidents and service requests).

## Consequences

- Change management (stage 5) will add `change` as a further ticket type, and link changes to the problems they fix.
- Reporting can count incidents per problem directly from `problem_id`.
