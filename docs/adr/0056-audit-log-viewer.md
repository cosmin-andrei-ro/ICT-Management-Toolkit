# ADR 0056 — Audit log viewer and record history

- **Status:** Accepted
- **Date:** 2026-12-18

## Context

Since ADR 0005, every change to a business record and every security event (sign-ins, rights, second factors, revealed secrets, exports) is written to `core_audit_logs`, append-only. Nothing in the application shows it: auditors and administrators had to query the database. CLAUDE.md §2.4 asks for complete traceability and exportable evidence, and ISO/IEC 27001 8.15 for logs that are reviewed.

## Decision

- **Record history:** a Core Livewire component, `RecordHistory`, placed at the end of the record pages through `<x-core::record-history :record :can-change />`. The pages are the desk ticket and the detail pages of every module.
  - The page decides who sees it: the people who may change the record (its own ability: `update`, or `work` for tickets) and the readers of the audit log (`core.audit.view`).
  - It is folded until opened and paginated, so a record page does not read its whole history.
  - The requester's portal ticket page does not show it.
- **Audit log page:** `/audit-log` (menu Compliance & audit), permission `core.audit.view`. The Auditor role now has it, next to administrators, team leads, security officers and compliance managers.
  - It shows security events by default; changes to records or everything on request.
  - Filters: event, person, record type and dates.
- **Organization scoping:** the log has no global scope (ADR 0005). `AuditLogQuery` returns the entries of the organization, plus organization-less entries (sign-ins, changes to people) whose actor or subject is one of its members. Failed sign-ins for unknown accounts belong to no organization and are shown to administrators only.
- **Presentation:** `AuditChanges` turns an entry into rows (field label, before, after). The ids of people are shown as names; other references stay as short ids, because the log keeps what was stored, not what it pointed to.
- **Export:** `core.audit_log` exports what the filters show to XLSX, one row per changed field with IP address and request id. It is queued and audited like every export (ADR 0016), and capped at 50,000 entries.

## Alternatives

- **A Filament resource for the log:** rejected. It would put the log behind the administration panel, which auditors and compliance managers do not open, and would not give per-record history.
- **Resolving every reference to its current name:** rejected for now. It needs every module's lookups, and it shows today's name instead of what was recorded.

## Consequences

- Every new record page should end with `<x-core::record-history>`.
- Reading by record uses the existing `(auditable_type, auditable_id, created_at)` index. Reading by organization uses `(organization_id, created_at)`. Partitioning (ADR 0005) is still to come with volume.
