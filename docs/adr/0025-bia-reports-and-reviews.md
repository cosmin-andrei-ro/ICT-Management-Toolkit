# ADR 0025 — BIA reports and periodic reviews

- **Status:** Accepted
- **Date:** 2026-11-17

## Context

A BIA is evidence: auditors ask for the register, for each process's analysis as approved, and for proof that it is reviewed (ISO 22301 §8.2.2 "at planned intervals", NIS2 Art. 21, DORA Art. 11). Reviews slip unless someone is reminded, and the coordinator needs to see at a glance what is late.

## Decision

- **Register export** (Excel or CSV, through Core exports: queued, audited, private, 7 days), for `bia.process.view`: one sheet of processes with the objectives in force (durations in minutes, so they sort and compute), version, approver and next review; one of the dependencies of the versions in force (CMDB items through the contract, and the others); one of the recovery gaps.
- **Process sheet** (`/bia/processes/{id}/sheet`): the version in force (or the latest when none is approved) on one page, to print or save as PDF from the browser — matrix, objectives, continuity measures, dependencies, submission and approval. Same access as the process. A server-side PDF waits for the PDF library decision.
- **Review reminders:** `bia:reviews:remind`, daily at 07:30, emails the owner and deputy of each active process whose review date is within `BIA_REVIEW_REMINDER_DAYS` (30) or past, once per review date (`review_reminded_for`, bookkeeping kept out of the audit trail), and not when a review is already open.
- **Coordinator digest:** `bia:digest`, Mondays at 07:45, to people who approve assessments: reviews overdue, processes never assessed, assessments waiting for approval — counts only, and nothing when all are zero.
- **Overview** on the process list: processes per tier, never assessed, reviews overdue, assessments being filled in and waiting for approval.

## Alternatives considered

- **A daily digest:** more timely, but a coordinator would get the same counts every day; owners get the daily, per-process reminder instead.
- **Reminding until done:** harder to ignore, but noisy; the weekly digest keeps overdue reviews in sight.

## Consequences

- The BCM module can build plans on the register and the gaps, and its exercises can confirm declared recovery capabilities.
- When a PDF library is chosen, the process sheet can be offered as a PDF without changing its content.
