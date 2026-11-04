# ADR 0032 — Risk treatment and acceptance

- **Status:** Accepted
- **Date:** 2026-11-25

## Context

ISO/IEC 27001:2022 §6.1.3 and §8.3 ask for a risk treatment plan and for risk owners' approval of the plan and acceptance of the residual risks; ISO/IEC 27005 names the options (modify/mitigate, share/transfer, avoid, retain/accept). The owner decided that accepting a risk above the organization's threshold needs a risk manager, never the owner alone (ADR 0031).

## Decision

- **Treatment on the risk:** option (mitigate, transfer, avoid, accept), plan, and the **residual** likelihood × impact expected once treated. Levels and the register use the residual score when assessed.
- **Actions** (`risk_actions`): title, owner, due date, planned → in progress → done or cancelled; overdue shown; optionally tracked as a **problem** in Ticketing through Core's `ProblemTickets` (impact from the risk's level, the risk's CMDB items linked), as for exercise findings (ADR 0027).
- **Acceptance** (`risk_acceptances`) of the residual risk with a justification:
  - residual score **below** the approval threshold: accepted at once by the owner (or a risk manager);
  - **from** the threshold: pending until a holder of `risk.acceptance.approve` (risk managers) approves or rejects it (reason required) — **never the risk's owner nor the requester**, re-checked under a row lock; one pending request per risk; risk managers are emailed, owner and requester told of the decision.
  - valid until the risk's next reassessment; **a different residual score voids it** (approved → *lapsed*, pending → *withdrawn*).
- **Reassessment:** saving the assessment is the periodic review — it sets the next review a full interval later; `risk:reviews:remind` (daily 08:00) emails owners `RISK_REMINDER_DAYS` (30) before, once per date.
- **Score history** (`risk_score_history`): every change of inherent or residual scores, append-only, with who and when.

## Alternatives considered

- **Treatment plans as a separate entity grouping several risks:** closer to some ISMS tools, but most organizations treat risk by risk; actions already carry owners and dates.
- **Approval of every acceptance:** heavy for low risks; the threshold lets owners accept what the organization deems tolerable.
- **Actions as service requests:** they come with an SLA meant for a waiting requester; a problem fits proactive work on a cause.

## Consequences

- Reports (part D) can show inherent vs residual heat maps, overdue actions and acceptances due for renewal.
- Suggestions (part C) will arrive as draft risks to assess and treat through this flow.
