# Audit module

Compliance with regulations and standards: frameworks and their requirements, the organization's controls and the requirements they meet. Code: `modules/Audit/`. Decisions: [ADR 0034](../adr/0034-compliance-frameworks-and-controls.md), [ADR 0035](../adr/0035-compliance-evidence-and-control-tests.md), [ADR 0036](../adr/0036-audits-findings-and-corrective-actions.md).

## Concepts

- **Framework** (`audit_frameworks`): a regulation, standard or internal policy; key (`iso27001`, `nis2`, `gdpr`, `dora`, or `custom-<slug>`), name, version, description, whether it keeps a Statement of Applicability, active or archived. Shipped catalogs in `resources/frameworks/*.php` (ISO/IEC 27001 Annex A identifiers with the toolkit's own titles only).
- **Requirement** (`audit_requirements`): reference (unique in the framework), title, wording or guidance, section, position; applicability (null until decided, true, false) with its justification (required when not applicable) and who decided.
- **Control** (`audit_controls`): C-001 per organization, name (unique, case-insensitive, among controls not deleted), description, owner, status (planned, partial, implemented, retired), implementation notes; soft deleted.
- **Mapping** (`audit_control_requirement`): which requirements a control meets, in any framework.
- **Evidence** (`audit_evidence`): a record of another module (`linked_type`, `linked_id`, title kept) or a document (Core attachment on the evidence), with what it shows, optionally valid until a date and the test that produced it; soft deleted.
- **Control test** (`audit_control_tests`): tested on, tester, result (effective, partially effective, ineffective), method, findings. The control's `test_interval_months`, `last_tested_on`, `next_test_on` (and `test_reminded_for`, reminder bookkeeping).
- **Audit** (`audit_audits`): AUD-001 per organization, title, internal or external (audit body), scope, frameworks covered (`audit_audit_framework`), lead auditor, dates, status (planned, in progress, completed with a conclusion, cancelled); soft deleted.
- **Finding** (`audit_findings`): F-001 per organization, from an audit or a control test, severity (major or minor nonconformity, observation, opportunity), control and requirement concerned, owner, due date, root cause, status (open, verification, closed), who submitted it and who verified and closed it, with notes.
- **Corrective action** (`audit_finding_actions`): title, owner, due date, status (planned, in progress, done, cancelled), the service desk problem raised for it.
- **Requirement state** (computed, `RequirementState`): undecided, not applicable, no control, controls planned, partly met, met. **Coverage** (`Coverage`): requirements by state, and met / applicable.

## Screens

- `/compliance`: frameworks with their coverage and requirements by state; add a framework from the toolkit or one of your own (compliance managers); archived frameworks on demand.
- `/compliance/frameworks/{id}`: requirements by section with their controls and state; search and filter by state; decide applicability with a justification and the requirement's wording; add requirements; archive or restore.
- `/compliance/controls`: controls with owner, status and number of requirements; filters by status and *not mapped*.
- `/compliance/controls/new`, `/compliance/controls/{id}/edit`: a control (compliance managers).
- `/compliance/controls/{id}`: implementation (status and notes, by the owner or a compliance manager); requirements it meets, mapped by framework and search; evidence: link a record (searched through Core's `LinkedRecords`) or upload a document; tests: history and recording; delete.
- Control register: next test, overdue in red, filter *Test overdue*.
- `/compliance/audits`, `/compliance/audits/new`, `/compliance/audits/{id}` (`/edit`): audits; start, complete with a conclusion, cancel, reopen; their findings.
- `/compliance/findings`: findings not closed, closed or all; by severity; overdue.
- `/compliance/findings/new?audit=` or `?test=`, `/compliance/findings/{id}/edit`: a finding (compliance managers); from a test, prefilled with the control and its owner.
- `/compliance/findings/{id}`: root cause and corrective actions (owner or compliance manager; action owners report progress; *Track in the service desk*), submit for verification, close or reopen (a compliance manager other than the owner and the submitter).
- Control page: *Raise a finding* on a test that was not fully effective.
- `/compliance/controls/mine` (*My compliance tasks*): controls, open findings and corrective actions of the signed-in person, for those without `audit.compliance.view`.

## Records evidence can link to

Registered with `LinkedRecords::registerSearch` by their modules: `risk.risk`, `applications.application`, `bcm.plan`, `bcm.exercise`, `bia.process`, `core.contract`, `ticketing.ticket` (search only for `ticketing.ticket.view_all`).

## Scheduled tasks

- `audit:tests:remind` (daily 08:05): the owner of each control is emailed `AUDIT_TEST_REMINDER_DAYS` (14) before its next test, once per date.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `audit.compliance.view` | compliance-manager, auditor, security-officer, risk-manager | Read frameworks, applicability and controls |
| `audit.compliance.manage` | compliance-manager | Add frameworks and requirements, decide applicability, keep controls and their mapping, plan audits, raise findings, verify and close them |

A control's owner updates its status and implementation notes, adds evidence and records tests without a permission; a finding's owner records its root cause and actions and submits it; an action's owner reports its progress. Compliance managers and auditors also read risks, applications, continuity plans, business processes, suppliers and contracts, to open what evidence points to.

## Plan

1. Frameworks (ISO/IEC 27001 Annex A, NIS2, GDPR, DORA, your own), applicability, controls mapped across frameworks, coverage — **done**.
2. Evidence: links to toolkit records, uploaded documents, periodic control tests with reminders to owners — **done**.
3. Audits (internal and external), findings, corrective action plans (optionally service desk tickets), closure verified by someone other than the owner — **done**.
4. Reporting: Statement of Applicability in Excel and PDF, compliance report, weekly digest, compliance gaps as risk signals.
