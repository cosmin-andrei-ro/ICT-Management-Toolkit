# ADR 0029 — Application portfolio and structured documentation sheet

- **Status:** Accepted
- **Date:** 2026-11-21

## Context

CLAUDE.md §9 asks that every application of the company has its mandatory documentation as a **structured form with an approval flow**, versioned, aligned exactly with the official template. The owner supplied the template, *Application Technical Documentation*: 16 chapters plus change history, document control and approvals (recorded in `docs/templates/application-technical-documentation.md`). Most chapters are tables with many columns and dropdowns; several reuse what other modules already hold (suppliers and contracts, CMDB items, BIA objectives, BCM plans).

## Decision

- **Module `Applications`** (`applications_*` tables): the register of applications (code, name, department, business owner, IT / application owner, deputy) and their documentation sheet in versions.
- **The sheet is defined once, in code** (`SheetSchema`): chapters → numbered sections (as in the template, "1.4") → typed fields (text, option, choices, person, supplier, contract, table of columns, contacts per team…). Forms, validation, completeness on submission and read-only display are generated from it, so later chapters are mostly definitions plus translations (labels, guidance and examples from the template).
- **Storage:** a version's content is `jsonb`, keyed by section, normalized by `SheetForm` (free "Other" text resolved, empty rows dropped, references to people, suppliers and contracts checked within the organization, links limited to http/https). A version is an immutable snapshot once in force, which a document needs; the values summarized for lists (status, criticality, version, review dates) are copied to the application. Where data belongs to another module (CMDB items, BIA objectives, BCM plans), later chapters reference it through Core contracts rather than copy it.
- **Dropdowns** (owner's decision): the template's lists were not part of it; each organization starts from proposed defaults and administrators edit them (`applications_option_values`). Sheets store the key, so renaming a value renames it everywhere; values are deactivated, never deleted. "Other" with free text where the template allows it.
- **Versions and approvals** (owner's decision): the first version and versions marked *major* (architecture, data, security, users, integrations) are approved by the **business owner**, the **IT / application owner** and a **security officer** — one approval each, never by the person who submitted it, one person one approval; application managers may stand in for either owner. The third approval brings the version into force; any approver may return it with a reason, which clears the approvals given. *Minor* versions come into force when published and are logged in the change history. Versions are numbered when they come into force: 1.0, then 1.1 (minor) or 2.0 (major). The next review is due 12 months later (`APPLICATIONS_REVIEW_MONTHS`).
- **Roles** (owner's decision): new default roles **Application manager** (register, any sheet, dropdowns) and **Security officer** (security approval); owners and deputies edit their own applications' sheets without a permission; IT staff (agents, team leads, continuity coordinators) read.
- **PDF** (owner's decision): the full sheet is exported through Gotenberg — an optional container with headless Chrome (MIT) — in a later step, with its own ADR; the browser's print remains without it. mPDF was excluded (GPL-2.0-only is incompatible with AGPLv3).

## Alternatives considered

- **One table per template table** (environments, firewall rules, certificates…): about 40 tables with their versioning copies and screens; every template change would need migrations. Kept for data other modules must query (later: certificates for expiry reminders may be indexed from the JSON or projected to a table).
- **Free-form document (rich text):** not structured, not checkable, not reusable — rejected by CLAUDE.md §9.
- **Approval of every version:** slows small corrections; rejected by the owner in favour of approving v1.0 and major versions.

## Consequences

- Adding a chapter is a schema definition, translations and, where needed, a new field type.
- Reports across applications read the JSON (`jsonb` operators, GIN index if needed).
- A template change is a schema change: older versions keep their content; new sections start empty.
