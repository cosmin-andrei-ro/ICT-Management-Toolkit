# Audit module

Compliance with regulations and standards: frameworks and their requirements, the organization's controls and the requirements they meet. Code: `modules/Audit/`. Decisions: [ADR 0034](../adr/0034-compliance-frameworks-and-controls.md).

## Concepts

- **Framework** (`audit_frameworks`): a regulation, standard or internal policy; key (`iso27001`, `nis2`, `gdpr`, `dora`, or `custom-<slug>`), name, version, description, whether it keeps a Statement of Applicability, active or archived. Shipped catalogs in `resources/frameworks/*.php` (ISO/IEC 27001 Annex A identifiers with the toolkit's own titles only).
- **Requirement** (`audit_requirements`): reference (unique in the framework), title, wording or guidance, section, position; applicability (null until decided, true, false) with its justification (required when not applicable) and who decided.
- **Control** (`audit_controls`): C-001 per organization, name (unique, case-insensitive, among controls not deleted), description, owner, status (planned, partial, implemented, retired), implementation notes; soft deleted.
- **Mapping** (`audit_control_requirement`): which requirements a control meets, in any framework.
- **Requirement state** (computed, `RequirementState`): undecided, not applicable, no control, controls planned, partly met, met. **Coverage** (`Coverage`): requirements by state, and met / applicable.

## Screens

- `/compliance`: frameworks with their coverage and requirements by state; add a framework from the toolkit or one of your own (compliance managers); archived frameworks on demand.
- `/compliance/frameworks/{id}`: requirements by section with their controls and state; search and filter by state; decide applicability with a justification and the requirement's wording; add requirements; archive or restore.
- `/compliance/controls`: controls with owner, status and number of requirements; filters by status and *not mapped*.
- `/compliance/controls/new`, `/compliance/controls/{id}/edit`: a control (compliance managers).
- `/compliance/controls/{id}`: implementation (status and notes, by the owner or a compliance manager); requirements it meets, mapped by framework and search; delete.
- `/compliance/controls/mine`: controls the signed-in person owns, for owners without `audit.compliance.view`.

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `audit.compliance.view` | compliance-manager, auditor, security-officer, risk-manager | Read frameworks, applicability and controls |
| `audit.compliance.manage` | compliance-manager | Add frameworks and requirements, decide applicability, keep controls and their mapping |

A control's owner updates its status and implementation notes without a permission.

## Plan

1. Frameworks (ISO/IEC 27001 Annex A, NIS2, GDPR, DORA, your own), applicability, controls mapped across frameworks, coverage — **done**.
2. Evidence: links to toolkit records, uploaded documents, periodic control tests with reminders to owners.
3. Audits (internal and external), findings, corrective action plans (optionally service desk tickets), closure verified by someone other than the owner.
4. Reporting: Statement of Applicability in Excel and PDF, compliance report, weekly digest, compliance gaps as risk signals.
