# ADR 0060 — Configurable data retention

- **Status:** Accepted
- **Date:** 2026-12-22

## Context

GDPR's storage limitation (Art. 5(1)(e)) and CLAUDE.md §6 ("configurable retention") ask that data not be kept longer than needed. Until now only the inbound e-mail log (90 days) and exports (7 days) were pruned. Closed tickets, which hold requesters and their words, were kept forever, and so was the audit log (ADR 0005 left its pruning to an "external job" that never existed). The owner chose (2026-11-03):

- defaults of 3 years for closed tickets, which are anonymized rather than deleted, and 5 years for the audit log;
- periods set per organization;
- audit entries of no organization kept as long as the organization that keeps its log longest.

## Decision

- **A Core registry, `DataRetention`.** Modules register a policy:
  - a key, a label and a help text that says what happens to older records;
  - default years (at least 1);
  - `fn (organization, cut-off): int`.

  Core never touches module tables.
- **Per-organization periods** (`core_retention_settings.periods`, audited). A policy maps to years, or to `null` to keep forever; a policy never set keeps its default. They are set under Administration → Access → Data retention (`core.personal_data.manage`). Values run from 1 to 50 years.
- **`core:retention`**, every night at 03:30 (`ApplyRetention`):
  - in each organization, it applies each policy with the cut-off "today minus the period", and records `retention_applied` with what it handled;
  - then it purges audit entries of no organization after the **longest** audit period among organizations, and never while one keeps its log forever.
- **Audit log policy** (`core.audit_log`, 5 years): `core_purge_audit_log(organization, older_than)` deletes the organization's older entries. The immutability trigger allows DELETE only while that function has set `core.audit_retention` for the transaction. UPDATE keeps its own narrow exception (ADR 0059), and TRUNCATE stays forbidden.
- **Ticket policy** (`ticketing.closed_tickets`, 3 years from closing, `AnonymizeClosedTickets`):
  - **kept:** number, type, category, service, queue, assignee, dates, SLA timers, status history and CMDB links, so reports and statistics stay correct;
  - **removed:** requester (`requester_id` becomes nullable), title (replaced by "Anonymized ticket"), description, resolution note, custom fields, watchers, comments, attachments and their files, and the sender and subject of linked inbound e-mails.
  - The text must not outlive the ticket in the audit log. `AuditRedaction::removeFields()` (`core_redact_record_in_audit_log()`, under the same UPDATE exception) takes those fields out of the ticket's entries, and the body and file names out of its comments' and files' entries. Deletions run at query level, so that removing the text does not log it again.

## Alternatives

- **Deleting closed tickets:** rejected by the owner. Statistics, SLA reports and problem analysis need them.
- **One period for the whole installation (`.env`):** rejected. Organizations are separate legal entities with their own obligations.
- **Pruning the audit log by partition drops:** postponed until partitioning exists (ADR 0005). A dated DELETE is enough at today's volumes.

## Consequences

- Choosing a period is the organization's decision, and the manual says so. Too short a period can destroy evidence an audit still needs.
- IT support must cope with tickets without a requester. The desk, change review, queue and API show "Anonymized" or null.
- New kinds of personal records should register a retention policy.
- DBA-level access can still use the exceptions. As before, the trigger protects against the application, not against the DBA.
