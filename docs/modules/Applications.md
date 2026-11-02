# Application portfolio module

The register of the company's applications and their technical documentation, following the owner's template ([structure](../templates/application-technical-documentation.md)). Code: `modules/Applications/`. Decisions: [ADR 0029](../adr/0029-application-portfolio-and-documentation-sheet.md).

## Concepts

- **Application** (`applications_applications`): code (unique, upper case), name, department owner and e-mail, business owner, IT / application owner, deputy; status, criticality, version in force and review dates copied from the sheet in force.
- **Sheet version** (`applications_sheets`): the documentation, as `jsonb` keyed by template section (`1_4` for 1.4 Environments), defined by `SheetSchema`. Draft → submitted → in force (approved) / returned; superseded when a later version comes into force. One open and one in-force version per application. Major or first versions need three approvals; minor ones come into force when published. Numbered 1.0, 1.1, 2.0 when in force.
- **Approvals** (`applications_sheet_approvals`): business, technical and security, one each per submitted version.
- **Diagrams**: images (PNG, JPEG, WebP, GIF — never SVG) and .drawio sources are Core attachments of the application, so later versions keep them; the sheet stores their ids. Images are shown inline by `/applications/diagrams/{attachment}` (authorized, `nosniff`, sandbox CSP); sources are downloaded through Core's attachment controller. The data-flow diagram (2.2) is also drawn as SVG from the integrations (2.4).
- **Data categories** (2.1): the template's 21 categories and their elements, fixed in code (`DataCategories`), plus free "Other".
- **Network diagram** (4.2): drawn as SVG from the firewall rules (4.3) by `NetworkDiagram` — one box per source or destination, placed in columns by traffic order, one arrow per rule with port and protocol.
- **CMDB items** (5.2): linked to the version through Core's `ConfigurationItems` contract (`applications.sheet` links, audited on the version); a new version starts with the links of the version in force; the CMDB item page lists the application.
- **Technical accounts** (7) and later secrets record only where a credential is kept (vault), never the credential.
- **Continuity** (10): business processes of the BIA linked by id (`BusinessProcesses`), their RTO, RPO and tier shown with the strictest values; what the linked CMDB components depend on (`dependencyIdsOf`) with their recovery capability and last test; the continuity and recovery plans covering them (new `ConfigurationItems::recordIdsLinkedTo` + `LinkedRecords`). Shown, never copied into the sheet.
- **Certificates** (8.1): an *Expires on* date, not in the template, drives the reminders.
- **Decommissioning** (15.2): the template's twelve steps added with one click; **glossary** (16): the template's standard terms shown, application terms added.
- **Permissions matrix** (3.4): rows are the functionalities of 2.3, columns the roles of 3.3; stored by name.
- **Excel export** (`applications.portfolio`): applications, data processed, integrations, components and certificates of the versions in force; never secrets or technical accounts.
- **Dropdown values** (`applications_option_values`): per organization and list; defaults on first use; deactivated, never deleted.

## Screens

- `/applications`: the portfolio with search and filters (status, criticality, not documented or review overdue).
- `/applications/new`, `/applications/{id}/edit`: identity and owners (application managers).
- `/applications/{id}`: identity, document control (version, last and next review), change history with approvals; write, continue or update the documentation.
- `/applications/sheets/{id}`: the sheet chapter by chapter (document control and the 16 chapters of the template); kind of change and description; submit or publish; approve as business owner, IT owner or security officer, or return.
- `/applications/sheets/{id}/document`: the full documentation as the template lays it out (cover, change history, document control, approvals, contents, every chapter), to print; `/applications/sheets/{id}/pdf` the same as PDF through Gotenberg when `GOTENBERG_URL` is set (throttled, audited as an export).
- `/applications/mine`: applications the signed-in person owns, for owners without `applications.application.view`.
- Administration → Application portfolio → Dropdown values.

## Links created on approval

When a version comes into force, the application gets (or keeps) its own CMDB item of type *application* — identifier = the application code, criticality from 1.3 for a new item — related *runs on* to the components linked in 5.2, through `ConfigurationItems::ensureItem`; its CMDB page lists the application. Business processes (10.1) are linked to the BIA by the owners. Risk entries and Documentation pages will follow when those modules exist.

## Scheduled tasks

- `applications:reviews:remind` (daily 07:45): business owner, IT owner and deputy are emailed `APPLICATIONS_REVIEW_REMINDER_DAYS` (30) before the yearly review, once per review date; a version being written silences it.
- `applications:digest` (Mondays 07:55): application managers get the applications not documented, overdue for review and versions waiting for approval, if any.

- `applications:certificates:remind` (daily 07:40): the technical owner and the deputy are emailed `APPLICATIONS_CERTIFICATE_REMINDER_DAYS` (30) before a certificate of the documentation in force expires (or once it has expired), once per certificate and expiry date (`applications_certificate_reminders`).

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
2. Chapters 2–3: data categories, functionalities, integrations, authentication, roles and permission matrix; data-flow diagram drawn from the integrations, plus upload — **done**.
3. Chapters 4–7: network, firewall rules, hosting, components linked to the CMDB, software, databases, technical accounts; network diagram, ERD upload — **done**.
4. Chapters 8–16: certificates with expiry reminders, secrets (vault location only), compliance, logging and alerts, continuity from the BIA and BCM, deployment, jobs, runbook, troubleshooting, decommissioning, glossary — **done**.
5. Full document and PDF through Gotenberg (ADR 0030), the application's CMDB item created on approval, yearly review reminders, weekly digest, Excel export — **done**.
