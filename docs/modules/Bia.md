# Business impact analysis (BIA) module

Business processes, the impact of their interruption over time, and their recovery objectives. Code: `modules/Bia/`. Decisions: [ADR 0023](../adr/0023-business-impact-analysis.md), [ADR 0024](../adr/0024-bia-dependencies-and-recovery-gaps.md), [ADR 0025](../adr/0025-bia-reports-and-reviews.md).

## Concepts

- **Business process** (`bia_processes`): name, department, owner and deputy, description, peak periods, status; the recovery objectives, tier and next review date of its approved assessment.
- **Assessment** (`bia_assessments`): one version of a process's analysis, with the impact matrix (`bia_impact_scores`), MTPD (derived, or set with a reason), RTO ≤ MTPD, RPO, MBCO, workarounds, minimum staff. Statuses: draft, submitted, returned, approved, superseded.
- **Dependencies** of an assessment: CMDB items (linked through Core's `ConfigurationItems` contract), and in `bia_dependencies` suppliers, locations, upstream processes, key roles (with a minimum number of people) and key people. Copied into the next version.
- **Impact categories** (`bia_impact_categories`) with descriptions of levels 1–5, and **time horizons** (`bia_time_horizons`), per organization; five and six defaults, created on first use.
- **Settings** (`bia_settings`): the unacceptable level (default 4) and the review interval (default 12 months).

## Screens

- `/bia`: overview (processes per tier, never assessed, reviews overdue, assessments in progress and waiting), processes with filters (department, tier, due for review, retired), export of the register to Excel (processes, dependencies, gaps).
- `/bia/processes/{id}/sheet`: the process's BIA on one page, to print; `/sheet/pdf` as PDF through Gotenberg (throttled, recorded as an export).
- `/bia/processes/new`, `/bia/processes/{id}/edit`: for the coordinator.
- `/bia/processes/{id}`: objectives in force, details, versions; start or continue an assessment.
- `/bia/assessments/{id}`: the matrix and the objectives; save, submit; approve or return.
- `/bia/gaps`: recovery gaps — each CMDB item used by approved processes (directly or through dependencies) with the strictest RTO/RPO required against the capability declared in the CMDB; criticality suggested from the tier, applied by people who may change CMDB items.
- `/bia/mine`: the processes the signed-in person owns or deputizes for, with what is expected of them.
- Administration → Business impact analysis: impact categories, time horizons, settings. Administration → Organization → Departments.

## Background

- `bia:reviews:remind`, daily at 07:30: owner and deputy of each active process whose review is due within `BIA_REVIEW_REMINDER_DAYS` (30) or overdue, once per review date, unless a review is open.
- `bia:digest`, Mondays at 07:45: counts of overdue reviews, processes never assessed and assessments waiting, to the people who approve; nothing when all are zero.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `bia.process.view` | continuity-coordinator, team-lead | See every process and assessment |
| `bia.process.manage` | continuity-coordinator | Create, edit, retire and delete processes; fill in any assessment |
| `bia.assessment.approve` | continuity-coordinator | Approve or return submitted assessments (never one's own) |
| `bia.settings.manage` | continuity-coordinator | Impact categories, time horizons, settings |

Owners and deputies see their processes and fill in their assessments without a permission. Administrators have every permission.

## API (ADR 0064)

Abilities: `bia:read`, `bia:write`. Permissions are the screens': `bia.process.view` / `bia.process.manage`, and a process's owner or deputy reads it.

| Method | Path | |
|---|---|---|
| GET | `/api/v1/bia/processes` | Filters `status`, `tier`, `department_id`, `owner_id`, `approved`, `search`; sorts `name`, `tier`, `rto_minutes`, `next_review_on`, `updated_at` |
| POST / GET / PATCH | `/api/v1/bia/processes`, `.../{id}` | Name (unique), department, owner and deputy (active members), description, peak periods, status; tier, MTPD, RTO and RPO come from the approved analysis |

Impact analyses are filled in, submitted and approved on screen.
