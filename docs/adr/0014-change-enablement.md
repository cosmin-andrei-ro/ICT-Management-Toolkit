# ADR 0014 — Change enablement with CAB and ECAB

- **Status:** Accepted
- **Date:** 2026-11-06

## Context

ITIL 4 change enablement makes changes to live systems safe: each change has a plan, a rollback plan and a window, and the right people authorize it before work starts. Routine work should not wait for a board. Urgent work, such as a critical security patch, cannot wait for the regular board, but still needs authorization and a look back afterwards.

## Decision

- **Changes are tickets of type `change`** (`CHG-` numbers), as planned in ADR 0008. They reuse queues, assignment, comments, attachments, status history and audit. They have no SLA clocks and never appear in the portal. Only `ticketing.change.create` (agents and team leads) creates them, through the desk; the portal and the API accept only incidents and service requests.
- **Change data lives in `ticketing_change_details` (1:1):** kind, risk, plans, window, board, approval status and round, and the post-implementation review. The database enforces the invariants with CHECKs: the window ends after it starts, a standard change has a template, `approved` ⇔ `approved_at`, review ⇔ `reviewed_at`. Composite FKs keep the board and the template in the change's organization.
- **Three kinds:**
  - **standard:** created from a `ticketing_change_templates` entry and authorized at once; only its window can be changed;
  - **normal:** authorized by a regular board;
  - **emergency:** authorized by a board flagged `is_emergency` (ECAB), never by a regular one, and the other way round.
- **Unanimity (owner's decision):** every active member of the board with `ticketing.change.approve` must approve, except the requester, whoever submitted the change (`submitted_by`) and whoever last wrote its plan (`planned_by`) (separation of duties; the last two added after the security review of 2026-11-03, so nobody can re-plan or send someone else's change and then approve it, e.g. on a board of one). One rejection, which needs a comment, rejects the change. Decisions are stored per round in `ticketing_change_approvals`, unique per round and person; the change row is locked while deciding, so concurrent decisions cannot both complete the round.
- **Rounds:** editing the plan or the window after submission withdraws the approvals (back to draft); submitting again starts a new round. The board approves what is actually done.
- **Implementation gate:** `ChangeGuard`, called by the ticket lifecycle, refuses `in_progress` and `resolved` before authorization, and refuses closing an emergency change without a post-implementation review (owner's decision). Automatic closing skips those changes too. Changes resolve as `implemented` or `rolled_back`.
- **Calendar:** submitted and authorized changes by window, with overlapping windows flagged. Overlap is a warning, not a block: two changes in the same window are often unrelated, and the CMDB does not exist yet to tell.
- **Boards are deactivated, not deleted,** so changes waiting for a board can still be decided. Membership is set through `SyncCabMembers`, which checks the organization and writes the audit trail (pivot writes bypass the model observer).
- **Notifications:** submission emails the approvers of the round with a link to the review page; authorization and rejection email the requester.

## Alternatives considered

- **N of M approvals or a chair's decision:** more flexible, but the owner chose unanimity; it can be added later as a per-board rule.
- **Emergency changes approved after the fact:** common, but the owner chose a small ECAB that approves before, plus the mandatory review.
- **A database exclusion constraint on windows:** rejected, see "Calendar".

## Consequences

- Changes are not yet linked to the problems they fix (announced in ADR 0012) or to configuration items; both come with the CMDB work, which can also turn window overlaps on the same CI into real conflicts.
- `ChangeApproved` is the hook for later automation (CMDB updates, release notes).
