# ADR 0023 — Business impact analysis

- **Status:** Accepted
- **Date:** 2026-11-15

## Context

Continuity planning starts from the business: which processes matter, how fast an interruption becomes unacceptable, and how quickly each must be back (ISO 22301 §8.2.2, ISO 22317, NIS2 Art. 21 business continuity, DORA Art. 11). IT then plans recovery for what those processes need. The analysis is owned by the business, approved by someone accountable for continuity, reviewed periodically, and kept as evidence.

## Decision

- **Module `Bia`** with its own tables (`bia_*`), screens, permissions, manual and tests.
- **Roles (owner's decisions):** a new default role, **BIA coordinator**, creates processes, names their owners, approves assessments and configures the method. A process's **owner and deputy**, whatever their role (usually plain employees), fill in its assessments: their rights come from ownership, checked in the policies. Team leads read the BIA to plan IT recovery. Core departments get an administration screen, since processes belong to them.
- **Method (owner's decision): an impact matrix over time.** Impact categories (five defaults: financial, legal and regulatory, reputation, operations, customers and partners, each with a description of levels 1–5) are scored at time horizons (six defaults: 4 hours to 1 month). Both are configurable per organization; a horizon's duration is fixed once scored, and a scored category is deactivated rather than deleted.
- **MTPD** is derived: the first horizon at which any category reaches the organization's **unacceptable level** (default 4). It can be set differently only with a written reason. The **RTO** may not exceed the MTPD (checked in the application and by a CHECK constraint); the **RPO**, minimum level of service (MBCO), manual workarounds and minimum staff complete the assessment. Impact may not decrease with time. The **tier** follows from the RTO: 1 within 4 hours, 2 within a day, 3 within 3 days, 4 later.
- **Versioned assessments:** draft → submitted → approved, or returned with a mandatory comment. One open version per process and one approved version at a time (partial unique indexes); approving supersedes the previous one. A new version starts as a copy of the approved one. Approved and superseded versions never change; the threshold in force is stored with the approval.
- **Separation of duties:** whoever owns the process or submitted the version cannot approve it, even with the permission (checked again under a row lock).
- **Process summary:** the objectives of the approved version, the tier and the next review date (approval + review interval, default 12 months) are copied onto the process for lists, reports and the gap analysis.
- **Audit:** processes and assessments are audited; impact scores, a separate table, are recorded on the assessment as a whole matrix (before and after).
- **Notifications:** approvers when a version is submitted; owner, deputy and submitter when it is approved or returned. Scores and objectives are not emailed.

## Alternatives considered

- **One score per category, RTO entered directly:** faster to fill in, but the RTO would rest on nothing an auditor can follow.
- **A questionnaire built with the Core form schemas:** flexible, but the matrix and its derived MTPD need structure the generic forms do not have.
- **BIA held by IT:** quicker to start, but the business, not IT, knows what an hour without payroll costs.

## Consequences

- The second part adds dependencies (CMDB items, suppliers, locations, upstream processes, key roles) and the gap analysis against recovery capabilities declared in the CMDB; the third, reports, exports and review reminders.
- Changing the unacceptable level affects assessments approved afterwards; approved ones keep the level they were approved with.
