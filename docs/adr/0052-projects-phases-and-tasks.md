# ADR 0052 — Projects: phases, team and tasks

- **Status:** Accepted
- **Date:** 2026-12-14

## Context

Projects is the last module of CLAUDE.md §3. The owner chose:

- four phases with tasks on a board;
- stage gates approved by the sponsor, and RAG status reports;
- deliverables linked to other modules, with a closure checklist for new applications;
- a budget against actual costs.

This first step covers projects, phases, team and tasks.

## Decision

- **Project** (`projects_projects`):
  - a code allocated per organization (`PRJ-0001`, from `projects_sequences` with an upsert, like asset tags);
  - name, description, type (general, new application, infrastructure);
  - status (active, on hold, closed, cancelled);
  - sponsor, manager, planned dates.

  It is audited and soft deleted.
- **Phases** (`projects_phases`): every project gets the same four, *initiation*, *planning*, *execution* and *closure*, created with it. Initiation is under way from the start. A partial unique index allows **one active phase per project**. Each phase has its own planned dates. Moving to the next phase is the stage gate of the next step.
- **Team** (`projects_members`): people of the organization with an optional role in the project. Someone with open tasks cannot be removed.
- **Tasks** (`projects_tasks`):
  - a phase, a title, a description, a due date;
  - an assignee: the manager, the sponsor or a member;
  - a status (to do, in progress, blocked, done) and a position within its column.

  A CHECK ties `completed_at` to *done*. The board moves a task one column at a time with buttons, which works without drag-and-drop scripts (CSP, keyboard). Overdue tasks are marked.
- **Who does what:**
  - role **Project manager** (new default role): `projects.project.view` and `projects.project.manage`;
  - team leads manage projects too;
  - other staff roles (asset, application, continuity, security, risk, compliance managers and auditors) read every project;
  - anyone sees the projects they sponsor, run or work on, and the menu shows them;
  - a project's own manager changes it;
  - a person moves the tasks assigned to them, from the project or from **My tasks**;
  - closed and cancelled projects are read-only.

## Alternatives considered

- **Phases defined per project or per template:** flexible, but gates and reports are easier to read when every project has the same four. Names can be extended later.
- **Drag-and-drop with a JavaScript library:** nicer to use, but it adds a dependency and needs CSP work. Buttons move cards and stay accessible.
- **Tasks as service desk tickets:** the desk is for operations; project work has its own lifecycle and audience.

## Consequences

- Next steps add the gates and RAG reports, deliverables with the closure checklist, and the budget.
