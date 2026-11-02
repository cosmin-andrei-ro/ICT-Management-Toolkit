# Continuity and recovery (BCM/DRP) module

Business continuity plans (BCP) and IT disaster recovery plans (DRP). Code: `modules/Bcm/`. Decisions: [ADR 0026](../adr/0026-continuity-and-recovery-plans.md), [ADR 0027](../adr/0027-continuity-exercises.md).

## Concepts

- **Plan** (`bcm_plans`): type (BCP or DRP), name, owner and deputy, status; the version in force and next review date.
- **Plan version** (`bcm_plan_versions`): scope (BIA processes in `bcm_version_processes`, CMDB items through `ConfigurationItems`), scenarios, objective, activation criteria and authority, strategy, communication, assumptions. Statuses: draft, submitted, returned, approved, superseded.
- **Crisis team** (`bcm_team_members`): role, person of the organization or outside contact (business details), deputy flag, responsibilities.
- **Runbook** (`bcm_steps`): ordered steps by phase (activation, recovery, resumption, restoration), responsible role or person, estimated duration, system concerned. Activation + recovery are compared with the strictest RTO of the processes in scope.
- **Exercise** (`bcm_exercises`): a test of the plan in force — kind (tabletop, walkthrough, technical, full), scenario, date, facilitator, participants; planned, in progress, completed or cancelled; outcome, measured recovery time and the RTO at completion, summary and lessons learned. `bcm_plans.last_exercised_on` records the last completed one.
- **Exercise steps** (`bcm_exercise_steps`): the runbook copied when the exercise starts, with a result (done, with issues, failed, skipped) and the time taken.
- **Exercise items** (`bcm_exercise_items`): CMDB items to bring back; those recovered are confirmed in the CMDB on completion (`ConfigurationItems::confirmRecovery`: `recovery_verified_on`, `recovery_verified_minutes`, `recovery_verified_by`).
- **Findings** (`bcm_exercise_findings`): description, severity, step; raised once as a problem in the service desk through Core's `ServiceDeskTickets`.

## Screens

- `/bcm`: plans with filters (type, retired).
- `/bcm/plans/new`, `/bcm/plans/{id}/edit`, `/bcm/plans/{id}` (versions; write, continue or review).
- `/bcm/versions/{id}`: scope, scenarios and texts, team, runbook, recovery time against RTO; submit; approve or return.
- `/bcm/plans/{id}/print`: the version in force, to print or save as PDF for offline use.
- `/bcm/mine`: plans the signed-in person owns, for owners without `bcm.plan.view`.
- `/bcm/plans/{id}/exercises/new`: plan an exercise (plans in force only).
- `/bcm/exercises/{id}`: start, record step results, recovered systems and findings, raise problems, complete; printable once completed.
- CMDB item pages list the plans covering the item and when its recovery was last tested.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `bcm.plan.view` | continuity-coordinator, team-lead, agent | See every plan |
| `bcm.plan.manage` | continuity-coordinator | Create and edit any plan |
| `bcm.drp.manage` | continuity-coordinator, team-lead | Create and edit IT disaster recovery plans |
| `bcm.plan.approve` | continuity-coordinator | Approve or return submitted versions (never one's own) |

Owners and deputies edit and exercise their plans without a permission; whoever may edit a plan runs its exercises. Administrators have every permission.
