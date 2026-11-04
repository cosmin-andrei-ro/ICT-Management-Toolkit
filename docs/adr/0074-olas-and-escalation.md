# ADR 0074 — OLAs and escalation

- **Status:** Accepted
- **Date:** 2026-11-04

## Context

SLAs promise the requester a response and a resolution. Inside IT, a ticket passes between teams, and none of them sees how long it stayed with them. `CLAUDE.md` §3 asks for SLA/OLA and escalations. On 2026-11-04 the owner chose an OLA per team and a chain of notifications, with nothing moved automatically.

## Decision

- **A target per team (`ticketing_team_olas`),** one value for any priority, in working minutes on the organization's default calendar. Optional.
- **A clock per ticket and team (`ticketing_ola_timers`),** kept apart from the SLA clocks: SLA reports, filters and exports count "breached" clocks, and an OLA is not a promise to the requester. It reuses the same calendar and calculator.
- **The clock follows the ticket:** it starts when the ticket reaches a team with a target, pauses while waiting for the requester, ends when the ticket is resolved or moves to another team (the next team's starts then). Moving between two queues of one team changes nothing. Reopening starts a new clock. Problems have no clock, as they have no SLA.
- **The chain:** a missed OLA tells the team's lead. Each queue can name an **escalation contact**, told once per missed clock, SLA or OLA, if the ticket is still running after the queue's delay (60 minutes by default, in real time).
- **Nothing is reassigned,** so it stays clear who owns a ticket.

## Alternatives considered

- **OLA clocks as a third SLA metric:** less code, but every SLA report and filter would need excluding it, and one forgotten place would make SLA figures wrong.
- **Targets per priority for each team:** more exact, but a table per team to maintain. It can be added if one value proves too coarse.
- **Automatic move to an escalation queue:** faster, but tickets can bounce and owners can change unexpectedly (the owner's decision).

## Consequences

- Teams without a lead have nobody told of a missed OLA until the escalation contact, if any.
- The delay counts real time, not working time, so it can fire at night or at a weekend, when a clock has already been breached in working time.
- Changing a target applies to tickets reaching the team afterwards.
- OLAs are in the desk's ticket page and reports, but not in exports, webhooks or the requester's portal.
- The first time the schedule runs, overdue clocks and breached SLA clocks of queues given a contact can all escalate at once.
