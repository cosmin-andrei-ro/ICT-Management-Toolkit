# ADR 0035 — Compliance evidence and control tests

- **Status:** Accepted
- **Date:** 2026-11-28

## Context

A control is only as good as what shows it works. ISO/IEC 27001 (7.5, 9.1) asks for documented information as evidence of monitoring and of controls in operation; NIS2 Art. 21(2)(f) asks for policies and procedures to assess the effectiveness of the measures; DORA Art. 6(6) and Art. 24–25 ask for regular review and testing. Much of the evidence already lives in the toolkit: approved changes, continuity exercises and recovery tests, risk treatments, application documentation, supplier contracts.

## Decision (owner's choices)

- Part B of the Audit module. **Evidence** (`audit_evidence`) per control, of two kinds:
  - **a record of the toolkit**, by morph type and id, without a foreign key: a risk, an application, a continuity plan, an exercise or recovery test, a business process, a contract, a service desk ticket. The title is kept when linked, so the evidence still reads after the record is removed or for a viewer who may not open it.
  - **a document**, stored through Core's attachments (type checked from content, size limit, private disk, download only through the authorized controller), with an optional *valid until* date. An expired document is shown as such.
- **Finding records across modules:** Core's `LinkedRecords` gains `registerSearch` / `searchable` / `search`. Each module registers the types it owns, returning at most 20 records the viewer may see; resolving and searching share one mapping per module (`*Records` classes). Searching tickets is limited to people who see every ticket. Linking re-resolves the record as the actor sees it, so nobody links what they cannot open.
- **Control tests** (`audit_control_tests`): date (not in the future), tester, result (effective, partly effective, not effective), how it was tested (required), what was found, optionally the document it produced (stored as evidence of the test). The control gets a **test interval** in months; recording the latest test sets the next one; changing the interval reschedules from the last test, or from today.
- **Reminders:** `audit:tests:remind` (daily 08:05) emails the owner `AUDIT_TEST_REMINDER_DAYS` (14) before a test is due, once per due date; overdue tests are shown in red and filterable.
- **Who:** the control's owner or a compliance manager adds and removes evidence and records tests; whoever may see the control sees its evidence and downloads its documents. Compliance managers and auditors now read risks, applications, continuity plans, business processes and contracts, so they can open what evidence points to. They do not get access to tickets: those may contain personal data, and the title kept on the evidence is enough to read it.
- Evidence is soft deleted; everything is audited.

## Alternatives considered

- **Evidence only as uploads:** duplicates records the toolkit already keeps, and they go stale.
- **Foreign keys to each module's tables:** breaks module isolation (CLAUDE.md §5.1).
- **Test results changing the requirement state:** a failed test is a finding to handle (part C), not a silent downgrade of compliance; the state stays based on implementation, the test result is shown next to it.

## Consequences

- Other modules can reuse the search, for example to link records in audits (part C).
- An ineffective test is not yet turned into a finding: part C adds findings and corrective actions.
