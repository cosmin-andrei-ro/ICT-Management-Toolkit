# Application portfolio module

The register of the company's applications and their technical documentation, following the owner's template ([structure](../templates/application-technical-documentation.md)). Code: `modules/Applications/`. Decisions: [ADR 0029](../adr/0029-application-portfolio-and-documentation-sheet.md).

## Concepts

- **Application** (`applications_applications`): code (unique, upper case), name, department owner and e-mail, business owner, IT / application owner, deputy; status, criticality, version in force and review dates copied from the sheet in force.
- **Sheet version** (`applications_sheets`): the documentation, as `jsonb` keyed by template section (`1_4` for 1.4 Environments), defined by `SheetSchema`. Draft → submitted → in force (approved) / returned; superseded when a later version comes into force. One open and one in-force version per application. Major or first versions need three approvals; minor ones come into force when published. Numbered 1.0, 1.1, 2.0 when in force.
- **Approvals** (`applications_sheet_approvals`): business, technical and security, one each per submitted version.
- **Dropdown values** (`applications_option_values`): per organization and list; defaults on first use; deactivated, never deleted.

## Screens

- `/applications`: the portfolio with search and filters (status, criticality, not documented or review overdue).
- `/applications/new`, `/applications/{id}/edit`: identity and owners (application managers).
- `/applications/{id}`: identity, document control (version, last and next review), change history with approvals; write, continue or update the documentation.
- `/applications/sheets/{id}`: the sheet chapter by chapter (document control, 1 General Information so far); kind of change and description; submit or publish; approve as business owner, IT owner or security officer, or return.
- `/applications/mine`: applications the signed-in person owns, for owners without `applications.application.view`.
- Administration → Application portfolio → Dropdown values.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `applications.application.view` | application-manager, security-officer, team-lead, agent, continuity-coordinator | See every application and sheet |
| `applications.application.manage` | application-manager | Register and edit applications; write any sheet; stand in for an owner's approval |
| `applications.sheet.approve_security` | security-officer | Give the security approval |
| `applications.settings.manage` | application-manager | Edit dropdown values |

Owners and deputies write their applications' sheets without a permission. Nobody approves a version they submitted; one person gives one approval.

## Plan

1. Register, versioned sheet with approvals, chapter 1, dropdowns, roles — **done**.
2. Chapters 2–3: data categories, functionalities, integrations, authentication, roles and permission matrix; data-flow diagram drawn from the integrations, plus upload.
3. Chapters 4–7: network, firewall rules, hosting, components linked to the CMDB, software, databases, technical accounts; network diagram, ERD upload.
4. Chapters 8–16: certificates with expiry reminders, secrets (vault location only), compliance, logging and alerts, continuity from the BIA and BCM, deployment, jobs, runbook, troubleshooting, decommissioning, glossary.
5. Full PDF through Gotenberg (ADR), links created on approval, yearly review reminders, Excel export.
