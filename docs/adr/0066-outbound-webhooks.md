# ADR 0066 — Outbound webhooks

- **Status:** Accepted
- **Date:** 2026-12-29

## Context

Integrations (chat, monitoring, an external service desk, a SIEM) want to hear when something happens instead of polling the API. On 2026-11-03 the owner chose:

- **Events:** tickets and changes, CMDB items and assets, risks and compliance, continuity.
- **Who configures them:** administrators only, per organization.
- **Payload:** the essentials plus the API link. The receiver fetches the details with its own token, under that token's abilities.

## Decision

- **Model:**
  - `core_webhooks`: name, HTTPS address, events, active flag, and a signing secret. The secret is generated, encrypted at rest, never logged, and shown once (on creation and on rotation).
  - `core_webhook_deliveries`: one row per event and webhook, with status (pending, delivered, failed), attempts, the receiver's HTTP status and error.
  - Both are scoped to the organization.
- **Registry:** modules register their event types in `WebhookEvents` (e.g. `ticket.created`). They publish through `PublishWebhookEvent`, passing the record's organization so events raised by the scheduler work too. This PR adds the Ticketing events:

  | Event | Raised when |
  |---|---|
  | `ticket.created` | A ticket is created |
  | `ticket.assigned` | A ticket is assigned |
  | `ticket.status_changed` | A ticket's status changes (`changes.status.from` / `to`) |
  | `ticket.resolved` | A ticket is resolved |
  | `ticket.sla_warning` | A ticket gets close to its SLA target (`changes.metric`) |
  | `ticket.sla_breached` | A ticket's SLA is breached (`changes.metric`) |
  | `change.submitted` | A change is submitted for approval |
  | `change.approved` | A change is approved |
  | `change.rejected` | A change is rejected |
  | `cmdb.item.created` / `.updated` / `.deleted` | A configuration item is created, changed (`changes.fields`: names only) or deleted |
  | `asset.assigned` / `asset.returned` | An asset is handed to someone, or back (the holder is not named) |
  | `risk.registered` | A risk is registered |
  | `risk.score_changed` | A new entry in the risk's score history (`changes.inherent_score` / `residual_score`) |
  | `risk.acceptance_decided` | An acceptance is approved or rejected (`changes.decision`) |
  | `finding.raised` / `finding.closed` | A finding is raised, or closed after verification (`changes.severity`) |
  | `continuity.plan_approved` | A plan version is approved (`changes.version`) |
  | `continuity.plan_activated` / `.activation_closed` | A plan is activated, or its activation closed (`changes.scenario`) |
  | `continuity.exercise_completed` | An exercise is completed (`changes.outcome`) |

  Ticketing publishes from its domain events. The other modules have none, so they publish from model events (`created` / `updated` / `deleted`). That way the screens, the API and imports all raise them. Continuity events link to the plan, with `changes.record_id` naming the version, activation or exercise.
- **Payload:**

  ```json
  {"id": "<delivery id>", "event": "ticket.created", "occurred_at": "...", "organization_id": "...",
   "data": {"id": "...", "reference": "INC-000123", "title": "...", "url": "https://.../api/v1/tickets/...", "changes": {}}}
  ```

  Descriptions, comments and people are never sent.
- **Delivery:**
  - A queued job, dispatched after the transaction commits, so a rolled-back change sends nothing.
  - Signed: `X-Webhook-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<body>">`, plus the headers `X-Webhook-Event` and `X-Webhook-Delivery`, which receivers use to drop duplicates.
  - A 2xx answer means delivered. Anything else is retried after 1 min, 5 min, 30 min, 2 h and 12 h, then the delivery is marked failed.
  - Administrators see every delivery and can send a failed one again, or send a test event.
- **SSRF:**
  - The address must use HTTPS and may not carry credentials.
  - Every address the host resolves to must be public: no loopback, link-local or metadata, reserved, private, carrier-grade NAT or NAT64 addresses.
  - The address is checked when it is saved and again at every delivery. The connection is then pinned to the address just checked (DNS rebinding).
  - Redirects are not followed.
  - `WEBHOOKS_ALLOW_PRIVATE_NETWORKS` and `WEBHOOKS_ALLOW_HTTP` open private networks and plain HTTP for installations whose receivers are internal. Reserved and loopback addresses stay closed either way.
- **Retention:** sent and failed deliveries are deleted after one year by default, a policy each organization can change (ADR 0060).

## Alternatives

- **Full records in the payload:** rejected. It would bypass the abilities and policies of the API, and copy personal data to every receiver.
- **Webhooks per user:** rejected by the owner. Integrations belong to the organization.
- **Unsigned deliveries, or a shared static token:** rejected. An HMAC with a timestamp lets receivers reject forged and replayed calls.

## Consequences

- Receivers must keep a token to fetch details. That is the point: what they read stays under the API's rules.
- Every save of a record that publishes events costs one indexed query for subscribed webhooks. Large imports therefore also send one event per record to the webhooks that subscribe.
- Delivery is at least once. Receivers must handle duplicates by delivery id.
