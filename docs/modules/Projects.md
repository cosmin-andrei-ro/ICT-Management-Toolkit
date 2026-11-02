# Projects module

IT projects: phases, team and tasks; then gates, status reports, deliverables and budget. Code: `modules/Projects/`. Decisions: [ADR 0052](../adr/0052-projects-phases-and-tasks.md).

## Concepts

- **Project** (`projects_projects`): code (`PRJ-0001`, per organization from `projects_sequences`), name, description, type (`general`, `application`, `infrastructure`), status (`active`, `on_hold`, `closed`, `cancelled`), sponsor, manager, planned start and end. Soft deleted.
- **Phase** (`projects_phases`): `initiation`, `planning`, `execution`, `closure` for every project; status (`pending`, `active`, `done`; one active per project), planned dates, started and completed times.
- **Member** (`projects_members`): a person on the team, with a role in the project.
- **Task** (`projects_tasks`): phase, title, description, assignee, due date, status (`todo`, `in_progress`, `blocked`, `done`), position on the board, completion time.

## Screens

- `/projects` (**Projects**): projects the person may see, open first; search by name or code.
- `/projects/new`, `/projects/{id}/edit`: the project form.
- `/projects/{id}`: phases with their dates, tasks as a board or a list (filter by phase), the team.
- `/projects/my-tasks`: a person's unfinished tasks in open projects.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `projects.project.view` | project-manager, team-lead, asset-manager, application-manager, continuity-coordinator, security-officer, risk-manager, compliance-manager, auditor | Read every project |
| `projects.project.manage` | project-manager, team-lead | Start projects and run any of them |

A project's manager changes it; sponsors and team members see it; assignees move their tasks.

## Plan

1. Projects, phases, team and tasks on a board — **done**.
2. Stage gates approved by the sponsor; RAG status reports; the portfolio.
3. Deliverables linked to other modules; the new-application checklist blocking closure.
4. Budget against actual costs; indicators, export, status report PDF.
