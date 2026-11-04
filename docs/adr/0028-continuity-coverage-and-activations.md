# ADR 0028 — Continuity coverage, reminders and activations

- **Status:** Accepted
- **Date:** 2026-11-20

## Context

Plans and exercises exist (ADR 0026, 0027); coordinators now need to see where the organization stands — which critical processes have no plan, which plans are not approved, overdue for review or never tested — and to be told without having to look. Auditors also ask how plans behaved in real disruptions (ISO 22301 §8.4.4, §10; DORA Art. 11(6) on post-incident reviews) and for the whole register as a file.

## Decision

- **Coverage report** (`/bcm/coverage`, *Risk & continuity → Continuity coverage*, `bcm.plan.view`): every active plan with its version in force, next review, last exercise and its outcome, next exercise due, and estimated recovery against the strictest RTO it covers; every assessed BIA process (through `BusinessProcesses::assessed`) with the plans in force covering it, whether one brings it back within its RTO and whether it was exercised. A filter keeps only what needs attention. Computed in a fixed number of queries (`ContinuityCoverage`).
- **Exercise cycle:** a plan in force is due for an exercise `BCM_EXERCISE_MONTHS` (12) after its last exercise, or after its approval when never exercised.
- **Reminders:** daily, the owner and deputy of a plan are emailed `BCM_REMINDER_DAYS` (30) before a review or an exercise is due, once per due date (`review_reminded_for`, `exercise_reminded_for`, bookkeeping kept out of the audit trail). A review already under way, or an exercise already planned, silences it. Weekly, continuity coordinators (`bcm.plan.approve`) get the counts that need chasing — processes without a plan, plans not approved, reviews and exercises overdue, versions waiting for approval, findings not handed to IT support, activations still open — and nothing when all are zero.
- **Activation log** (`bcm_activations`): whoever may edit a plan in force records a real activation — time, scenario, what happened — keeping the version and the strictest RTO of the moment. It can email the crisis team members with an account, and the owner and deputy, a link to the plan (high priority, no plan content in the email). When the service is back, the time and the lessons are recorded and the activation is closed; closed activations never change. Time to recover is compared with the RTO. The incident itself stays in Ticketing.
- **XLSX export** of the continuity register through Core's export pipeline (`bcm.register`, `bcm.plan.view`): plans, process coverage, exercises, findings, activations. Durations in minutes; crisis team contacts are left out.

## Alternatives considered

- **Activations from incidents automatically:** whether a disruption warrants the plan is a decision of the activation authority, not of a ticket's priority.
- **Reminding the crisis team rather than the owner:** owners are accountable for keeping plans current; the team is told when it matters, at activation.

## Consequences

- The coverage figures are the basis for continuity KPIs in the future Reporting module.
- Risk Assessment can use processes without a plan, or with plans that do not fit their RTO, as risk sources.
