# ADR 0067 — Monitoring alerts open incidents

- **Status:** Accepted
- **Date:** 2026-12-30

## Context

Monitoring tools detect most infrastructure failures before users do. An agent should not have to copy the alert into a ticket. On 2026-11-03 the owner chose:

- **Item:** match the CMDB item by identifier, then by exact name (the host name the tool already knows). Optionally the tool can send the item's id.
- **Recovery:** when the alert clears, the incident is resolved automatically, but only if nobody has started work on it. Otherwise an internal note is added and the agent decides.
- **Repeats:** an alert key identifies the problem. While its incident is open, repeats do not open new incidents; they are counted.
- **Formats:** an own, documented JSON format, plus Prometheus Alertmanager's native webhook.

## Decision

- **Endpoints:**
  - `POST /api/v1/monitoring/alerts` takes the own format: `key`, `status` (`firing` / `resolved`), `title`, `description`, `severity`, `item`, `item_id`, `source`.
  - `POST /api/v1/monitoring/alertmanager` takes the Alertmanager payload:
    - the key is the alert fingerprint;
    - the item is the `ci` label, else `instance` without its port, else `host`;
    - the title is `annotations.summary`, else `alertname`;
    - the severity is the `severity` label.
  - Both answer 202 with what happened: `created`, `repeated`, `resolved`, `noted` or `ignored`.
- **Who:**
  - The token needs the new ability `monitoring:write`.
  - Its user needs `ticketing.ticket.work`, in practice a service account with the agent role. The incidents are raised by that account.
- **What it does** (`ReceiveMonitoringAlert`):
  - **Firing alert:**
    - Opens an incident with channel `monitoring` (a new channel value).
    - The priority comes from the severity: critical → high impact, high urgency; major → medium impact, high urgency; minor and warning → medium, medium; info → low, low. It then goes through the priority matrix.
    - The incident is linked to the matched item through Core's `ConfigurationItems` contract (new method `findByReference`). No match, or more than one, leaves it unlinked, with the name in the description.
  - **Repeat** of a firing key whose incident is still open: `occurrences` and `last_seen_at` go up.
  - **Firing key whose incident was closed by hand:** the old alert is closed and a new incident opens.
  - **Cleared alert:**
    - If the incident is still New or Assigned, it is resolved with code *no action needed* and a note.
    - If it is in progress or pending, only an internal note is added.
    - An unknown key is ignored.
- **Storage:** `ticketing_monitoring_alerts` (key, source, status, ticket, item, occurrences, first/last seen, resolved). A partial unique index allows one firing alert per organization and key. When two identical alerts arrive at once, the second request becomes a repeat.
- **Screen:** the agent's ticket page shows the alert: status, occurrences, first and last seen, source and key.

## Alternatives

- **A new incident for every alert:** rejected. A flapping check would flood the queue.
- **Never resolving automatically:** rejected by the owner. Most alerts that clear by themselves need no work. When someone is working on the incident, the agent still decides.
- **Adapters for every tool:** only Alertmanager has a fixed payload. Other tools build their webhook body from templates, so they can send the own format.

## Consequences

- The token's user (the service account) becomes the incident's requester. On resolution, it is also the resolver and the assignee if there was none.
- Webhooks (ADR 0066) publish these incidents like any other: `ticket.created`, `ticket.resolved`.
