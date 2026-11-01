# ADR 0005 — Append-only audit trail in PostgreSQL

- **Status:** Accepted (implemented in Stage 1, Core module)
- **Date:** 2026-11-01

## Context

The platform must be audit-ready (ISO 27001, NIS2). Every change to a business entity must be recorded: who, when, which field, old value → new value. The history must not be editable or deletable from the application.

## Decision

- A single table, `core_audit_logs`, with:
  - `id` (uuid)
  - `organization_id`
  - actor (`user_id`, nullable for system actions)
  - `auditable_type` / `auditable_id`
  - `event` (created/updated/deleted/restored, plus security events)
  - `old_values` / `new_values` (jsonb)
  - `ip`, `user_agent`, `request_id`
  - `created_at`
- An `Auditable` trait fills the table through an Eloquent observer. Sensitive fields (passwords, secrets) are explicitly excluded.
- **Immutability is enforced in the database**: a `BEFORE UPDATE OR DELETE` trigger raises an exception, so the application has no way to change entries.
- Retention and archiving run as dedicated jobs under a separate database role, outside the web application.

## Rejected alternatives

- **owen-it/laravel-auditing:** a good model, but it does not enforce immutability in the database and does not fit UUIDs and per-organization scoping without significant changes.
- **Full event sourcing:** far too complex for current needs.

## Consequences

- Bulk operations through the query builder bypass observers. For business entities they must go through Actions that log explicitly.
- The table grows continuously and will need time-based partitioning (e.g. monthly) once volume requires it.
