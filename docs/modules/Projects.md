# Projects module

IT projects: phases, team and tasks; then gates, status reports, deliverables and budget. Code: `modules/Projects/`. Decisions: [ADR 0052](../adr/0052-projects-phases-and-tasks.md), [ADR 0053](../adr/0053-project-gates-and-status-reports.md), [ADR 0054](../adr/0054-project-deliverables-and-closure-checklist.md), [ADR 0055](../adr/0055-project-budget-indicators-and-report.md).

## Concepts

- **Project** (`projects_projects`): code (`PRJ-0001`, per organization from `projects_sequences`), name, description, type (`general`, `application`, `infrastructure`), status (`active`, `on_hold`, `closed`, `cancelled`), sponsor, manager, planned start and end. Soft deleted.
- **Phase** (`projects_phases`): `initiation`, `planning`, `execution`, `closure` for every project; status (`pending`, `active`, `done`; one active per project), planned dates, started and completed times.
- **Member** (`projects_members`): a person on the team, with a role in the project.
- **Gate request** (`projects_gate_requests`): from the phase under way to the next (`to_phase_id`), or to close the project (null); note; status (`pending`, `approved`, `rejected`; one pending per project); decided by the sponsor with a note.
- **Status report** (`projects_status_reports`): date (one per day), overall / schedule / budget / scope in `green`, `amber`, `red`; done, next steps, issues and risks; author.
- **Deliverable** (`projects_deliverables`): phase, title, required to close, linked record (`linked_type` / `linked_id` through Core `LinkedRecords`, title kept), delivered by and when.
- **Application** of a new-application project (`projects_projects.application_id`, through Core `Applications`).
- **Closure checklist** (`ClosureReadiness`, computed, not stored): application chosen, documentation approved, CMDB item, assessed risks, BIA processes with RTO. With the required deliverables, it blocks the closing gate.
- **Budget** (`projects_projects.budget_amount`, `currency`) and **costs** (`projects_costs`): category (`external_labour`, `licences`, `hardware`, `services`, `other`), description, amount (non-zero, negative for credits), date, supplier, invoice number. `BudgetFigures` gives spent, remaining, share and spending by category.
- **Task** (`projects_tasks`): phase, title, description, assignee, due date, status (`todo`, `in_progress`, `blocked`, `done`), position on the board, completion time.

## Screens

- `/projects` (**Projects**): projects the person may see, open first; search by name or code.
- `/projects/new`, `/projects/{id}/edit`: the project form.
- `/projects/{id}`: phases with their dates, tasks as a board or a list (filter by phase), the team.
- `/projects/my-tasks`: a person's unfinished tasks in open projects, and the gates waiting for them as sponsor.
- The project page has the **stage gate** (ask, approve, send back, history) and the **status reports** (write, history, due).
- The project page has **Deliverables** (add, link a record, mark delivered) and, for a new application, the **checklist** with the application chooser and what still blocks closure.
- The project page has the **Budget** (approved amount, costs, what is left) and **Print the status report** (`/projects/{id}/report`, `/pdf`).
- `/projects/portfolio`: open projects with phase, latest RAG, waiting gates and planned end.

## For other modules

Uses Core `Applications` (with `documented`, `configurationItemId`, `processIds`), `Risks::assessedCount()`, `BusinessProcesses::find()` and `LinkedRecords`.

## Scheduled tasks

- `projects:report-reminders` (Mondays 08:30): each manager hears of their active projects without a status report in `PROJECTS_REPORT_EVERY_DAYS` (14) days.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `projects.project.view` | project-manager, team-lead, asset-manager, application-manager, continuity-coordinator, security-officer, risk-manager, compliance-manager, auditor | Read every project |
| `projects.project.manage` | project-manager, team-lead | Start projects and run any of them |

A project's manager changes it and asks for gates; its sponsor decides on them; team members see it; assignees move their tasks.

## Plan

1. Projects, phases, team and tasks on a board — **done**.
2. Stage gates approved by the sponsor; RAG status reports; the portfolio — **done**.
3. Deliverables linked to other modules; the new-application checklist blocking closure — **done**.
4. Budget against actual costs; indicators, export, status report PDF — **done**.

## Indicators

Source `projects` in Core `Metrics`: `projects.red`, `projects.over_budget`, `projects.overdue_tasks`, `projects.gates_waiting`. Export type `projects.portfolio` (Portfolio page).

## API (ADR 0065)

`projects:read`, read-only.

- `GET /api/v1/projects`: all projects with portfolio access, otherwise the ones the user sponsors, manages or works in.
- `/projects/{id}`: phases, tasks, latest RAG report and `BudgetFigures`, including whether the project is over budget.
