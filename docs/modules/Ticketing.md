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

## Service levels (SLA)

Each ticket gets two clocks, **first response** and **resolution**, counted in working time with the organization's default business calendar ([ADR 0010](../adr/0010-sla.md)).

| Priority | First response | Resolution |
|---|---|---|
| Critical | 15 min | 4 h |
| High | 1 h | 8 h |
| Medium | 4 h | 3 working days |
| Low | 8 h | 5 working days |

- Defaults can be changed per organization in Administration → Service desk → SLA targets.
- Clocks pause while a ticket is pending (waiting for the requester), and restart when it is reopened.
- At 75 % of a target the assignee and the team lead are warned; at the due time the breach is recorded and they are notified again. If nobody owns the ticket, the whole queue team is notified.
- The desk shows the current deadline in the list, can filter tickets at risk, and shows both clocks on the ticket page.
- The scheduler must run for warnings and breaches (`ticketing:sla:check`, every minute).

## Screens

- **Self-service portal** (`/tickets`): my tickets, new ticket with attachments, ticket page with the public conversation, reply, confirm or reopen a resolution, withdraw a new ticket.
- **Service desk** (`/desk`): every ticket of the organization, most urgent first, filtered by status, queue, assignee and full-text search. The ticket page adds internal notes, assignment (limited to members of the queue's team), working states, resolution and triage.
- **Administration** (`/admin`, Service desk group): categories, queues, priority matrix. Teams are under Access.
- **Email notifications** (queued): ticket received, assigned to you, resolved, new reply. They contain the number, title and a link, never the comment text. Nobody is notified about their own action, and internal notes never reach requesters.

## API

`/api/v1/tickets` (list, create), `/api/v1/tickets/{id}` (show, with the comments the user may see), `/api/v1/tickets/{id}/comments` (add). Token abilities are `tickets:read` and `tickets:write`. See [ADR 0009](../adr/0009-rest-api.md) and [docs/api/openapi.json](../api/openapi.json).

```bash
php artisan core:api-token jane@example.com "Monitoring" --ability=tickets:write --days=90
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" https://<host>/api/v1/tickets?status=open
```

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `ticketing.ticket.create` | requester, agent, team-lead, cab-member | Raise tickets, see own and watched tickets |
| `ticketing.ticket.view_all` | agent, team-lead | See every ticket of the organization, including internal notes |
| `ticketing.ticket.work` | agent, team-lead | Triage, work, resolve; can be assigned |
| `ticketing.ticket.assign` | agent, team-lead | Route tickets to queues and people |
| `ticketing.settings.manage` | team-lead | Categories, queues, priority matrix |

Administrators have every permission.
