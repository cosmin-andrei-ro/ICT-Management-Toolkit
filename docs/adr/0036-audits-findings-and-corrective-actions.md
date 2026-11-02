# ADR 0036 — Audits, findings and corrective actions

- **Status:** Accepted
- **Date:** 2026-11-29

## Context

ISO/IEC 27001 asks for internal audits at planned intervals (9.2) and for nonconformities to be corrected, their causes found and the effectiveness of corrective actions reviewed (10.2). External audits (certification, regulators, customers) produce findings handled the same way. NIS2 Art. 21(2)(f) and DORA Art. 6(6) ask for the effectiveness of measures to be assessed and acted on. Control tests (ADR 0035) also find controls that do not work.

## Decision (owner's choices)

- Part C of the Audit module.
- **Audits** (`audit_audits`):
  - numbered AUD-001 per organization;
  - internal or external (with the certification body or audit firm);
  - scope, the frameworks covered, a lead auditor, dates;
  - planned → in progress → completed (conclusion required) or cancelled; can be reopened to add what was missed.
- **Findings** (`audit_findings`):
  - numbered F-001 per organization;
  - raised in an audit, or from a control test that was not fully effective (prefilled with the control, its owner and a severity following the result);
  - severity: major or minor nonconformity, observation, opportunity for improvement;
  - the control and the requirement concerned, an owner and a due date.
- **Corrective actions** (`audit_finding_actions`): title, owner, due date, status; optionally raised as a problem in the service desk through Core's `ServiceDeskTickets`, as risk treatment actions are.
- **Closure (ISO/IEC 27001 10.2):**
  1. The finding's owner records the root cause. For a nonconformity, the root cause and at least one corrective action are required.
  2. Every action is done or cancelled.
  3. The owner **submits** the finding for verification.
  4. A compliance manager **other than the owner and the submitter** verifies the correction is effective and closes the finding, or reopens it with a reason.
  5. Owner and verifiers are emailed at each step.
- **Who:**
  - compliance managers plan audits and raise and edit findings;
  - auditors and governance staff read them;
  - a finding's owner works it without a permission;
  - owners of corrective actions report their progress;
  - owners find their open findings and actions under *My compliance tasks*.
- Everything is audited: audits and findings on their own trail, actions on the finding.

## Alternatives considered

- **Corrective actions as risk treatment actions or tickets only:** findings need their own lifecycle (root cause, verification of effectiveness); tickets stay optional, for the IT work.
- **Auditors recording findings:** the owner chose a read-only Auditor role; findings are recorded by compliance managers, who may transcribe an external auditor's report.
- **Closing by the owner:** rejected; the owner's decision is independent verification.

## Consequences

- Part D reports findings (open, overdue, by severity) in the compliance report and the weekly digest, and turns open major nonconformities into risk signals.
