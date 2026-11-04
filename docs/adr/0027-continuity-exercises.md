# ADR 0027 — Continuity exercises and recovery tests

- **Status:** Accepted
- **Date:** 2026-11-19

## Context

A plan nobody has tested is a hypothesis. ISO 22301 §8.5 asks for an exercise programme with documented results and lessons; NIS2 Art. 21(2)(c) and DORA Art. 11(6) and 12 ask that continuity and recovery plans be tested and that backups be restored for real. The recovery capability IT declares in the CMDB (ADR 0024) is a claim until a test confirms it. Shortcomings found in a test need someone to fix them, and the owner wants them tracked in Ticketing.

## Decision

- **Exercises of a plan in force** (`bcm_exercises`): kind (tabletop, walkthrough, technical recovery test, full interruption test), scenario, date, facilitator, participants; planned → in progress → completed, or cancelled before completion. Whoever keeps the plan up to date (owner, deputy, `bcm.plan.manage`, `bcm.drp.manage` for DRPs) runs it; everyone who sees the plan sees its exercises.
- **The runbook as exercised:** starting an exercise copies the steps of the version in force (`bcm_exercise_steps`), so later versions do not rewrite the record. Each step gets a result (done, done with issues, failed, skipped) and the time it took. Completion requires a result for every step.
- **Measured recovery time against the RTO:** the activation and recovery steps add up to the measured time, kept on completion with the strictest RTO of that moment, next to the plan's estimate.
- **Verified recovery capability (owner's decision):** the systems in scope or worked on by the runbook are listed (`bcm_exercise_items`); the team marks which came back and in how long. On completion, recovered items are confirmed in the CMDB through a new `ConfigurationItems::confirmRecovery` method: `recovery_verified_on`, `recovery_verified_minutes` and the evidence (plan and exercise), audited on the item. The item page warns when the test took longer than the declared recovery time. The declared capability is not changed automatically: IT decides.
- **Findings become problems (owner's decision):** a finding (description, severity, optional step) can be raised once as a **problem** in Ticketing through a new Core contract, `ProblemTickets` (implemented by Ticketing, no-op without it). IT service management counts test results among the sources of proactive problem identification, and problems carry no requester-facing SLA. The problem's impact and urgency follow the severity; the configuration item of the step is linked to it. Problems can still be raised after completion; the finding keeps the ticket id and number.
- **Evidence:** a completed exercise never changes; it is printable (save as PDF) and every change is audited on it. The plan records when it was last exercised, for the reminders of the next part.

## Alternatives considered

- **Raise incidents or service requests:** incidents are for interruptions in production, requests come with an SLA meant for a waiting requester. A problem is what a failed recovery step is: a cause to find and fix.
- **Update the declared recovery time from the measured one:** a single test may be unrepresentative (a slow restore network that day, a partial test). The CMDB keeps both, and warns when they disagree.
- **Results on the plan's own steps:** each new version would rewrite the history of past tests.

## Consequences

- The BIA's recovery gaps can later use verified capability rather than declared capability.
- Exercise reminders and the coverage report (plans never or long not exercised) build on `last_exercised_on`.
- Without Ticketing, findings stay in the exercise; without the CMDB, no system is listed.
