# Ticketing module

Service desk following ITIL 4. Code: `modules/Ticketing/`. Data model: [ADR 0008](../adr/0008-ticketing-data-model.md).

## Concepts

- **Ticket:** an incident (`INC-…`) or a service request (`REQ-…`), with status, impact, urgency (from which the priority is derived), category, queue, assignee, watchers, comments (public or internal) and attachments.
- **Queue:** where tickets wait, routed to a Core team. Each organization can mark one queue as default.
- **Category:** a two-level classification for routing and reporting.

## Lifecycle

`new → assigned → in_progress ⇄ pending → resolved → closed`, plus `cancelled` from any open state.

- Starting work on an unowned ticket assigns it to the agent who started it.
- A requester's reply to a pending ticket brings it back into work.
- Resolved tickets close automatically after 5 days (`TICKETING_AUTO_CLOSE_AFTER_DAYS`, `ticketing:tickets:auto-close`, hourly). Until then the requester can reopen them.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `ticketing.ticket.create` | requester, agent, team-lead, cab-member | Raise tickets, see own and watched tickets |
| `ticketing.ticket.view_all` | agent, team-lead | See every ticket of the organization, including internal notes |
| `ticketing.ticket.work` | agent, team-lead | Triage, work, resolve; can be assigned |
| `ticketing.ticket.assign` | agent, team-lead | Route tickets to queues and people |
| `ticketing.settings.manage` | team-lead | Categories, queues, priority matrix |

Administrators have every permission.
