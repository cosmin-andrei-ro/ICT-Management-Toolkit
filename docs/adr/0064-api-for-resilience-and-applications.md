# ADR 0064 — API for business impact, continuity and the application portfolio

- **Status:** Accepted
- **Date:** 2026-12-27

## Context

The owner chose to expose the BIA, continuity plans and the application portfolio as well. Typical uses are:

- an intranet or a crisis tool that shows the plan in force when the network is partly down;
- reporting that reads recovery objectives;
- an HR or procurement system that registers applications and their owners.

These modules are mostly workflows: an impact analysis, a plan version or a documentation sheet is written, submitted and approved. The owner decided that approvals never go through the API (ADR 0061).

## Decision

- **Abilities:** `bia:read`, `bia:write`, `continuity:read`, `continuity:write`, `applications:read`, `applications:write`. Policies are the screens': managers manage the registers; owners and deputies read what is theirs.
- **Registers are writable; their approved content is read-only.**
  - **BIA:** create and change processes (name, department, owner, deputy, description, peak periods, status). Tier, MTPD, RTO and RPO are returned as the approved analysis set them.
  - **BCM:** create and change plans, with the screen's rule that a team lead manages only recovery plans (DRP). The plan's show view includes the version in force: objective, scenarios, activation criteria and authority, communication, strategy, processes, team with contact details, and steps, with the estimated recovery time. Exercises and activations are read-only.
  - **Applications:** create and change applications (unique code and name, department, owners). The documentation sheet in force is returned as stored, chapter by chapter.
- **People** (owners, deputies) must be active members of the organization: a shared rule, `Modules\Core\Http\Api\ActiveMember`, like the screens' pickers.
- **Not exposed:** editing, submitting or approving impact analyses, plan versions and documentation sheets, and running exercises or activations.

## Alternatives

- **Writing plan versions or sheets through the API:** rejected for now. They are long structured documents with approval workflows. Partial writes would bypass the reviews they exist for.
- **Returning every version:** rejected. Integrations need the version in force; history stays on screen.

## Consequences

- A crisis tool can show the approved recovery plan with its call tree. That data includes team members' phone numbers and e-mails, so tokens with `continuity:read` must be issued with care (CLAUDE.md §6, GDPR minimisation). The permission is the same as on screen.
- The API now covers every module where the owner chose write access. Read-only access to the remaining modules comes next.
