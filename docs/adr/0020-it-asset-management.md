# ADR 0020 — IT asset management: hardware assets

- **Status:** Accepted
- **Date:** 2026-11-12

## Context

IT must know which equipment the organization has, who has each piece, where it is, under which warranty or support contract, and how it left the organization. Auditors ask for the last part in particular: disposal with data wiping (ISO 27001 A.7.14, A.5.11 return of assets). The CMDB already records what IT runs; most assets (laptops, monitors) are not configuration items, while some (servers, switches) are both.

## Decision

- **Module `Itam`** with its own tables (`itam_*`), screens, permissions, manual and tests, depending only on Core.
- **Asset and configuration item stay separate (owner's decision).** An asset holds the financial and life-cycle side (purchase, warranty, holder, disposal); a configuration item the technical side. An asset may be linked to **one** item through Core's `ConfigurationItems` contract (the same `cmdb_ci_links` table tickets use), and the item page lists its assets through `LinkedRecords`. Without the CMDB, assets work without the link.
- **Categories** (`itam_categories`) are data, like CMDB types: a Core `FormSchema` of attributes, values in `properties` (`jsonb`). Ten defaults are created on first use, never recreated once removed.
- **Asset tags** are handed out per organization (`AST-000001`, prefix `ITAM_TAG_PREFIX`) by an atomic upsert on `itam_tag_sequences`, skipping tags already entered by hand. Tags and serial numbers are unique per organization regardless of case.
- **Life cycle:** ordered → in stock → in use → in repair → retired → disposed, plus lost. The allowed moves are in `AssetStatus::next()`; disposal is final and only follows retired or lost. The actions lock the asset row, so two people cannot hand out the same laptop at once.
- **Holders:** `itam_asset_assignments` records who had an asset, from when to when, and who handed it out and took it back. A partial unique index allows one open assignment per asset; the current holder is copied on the asset for fast lists, and the database refuses a holder unless the asset is in use or in repair. An asset in repair keeps its holder; any other move ends the assignment. Holders must be active members of the organization.
- **Disposal** records the date, the method (sold, donated, recycled, destroyed, returned to the lessor) and whether data was wiped (or that the asset stores none); the certificate goes in the asset's documents.
- **References to Core:** location, supplier and contract by composite foreign keys (`ON DELETE SET NULL` of the column only). The contract page lists the assets it covers through a new Core registry, `ContractDependents`.
- **Permissions (owner's decision):** agents see assets and hand them out (`itam.asset.view`, `itam.asset.assign`), because IT support does it daily; asset managers and team leads keep the register (`itam.asset.manage`) and the categories (`itam.category.manage`). Employees see their own equipment on **My equipment**, without costs, invoices or notes, and not the asset page.
- **Warranty reminders:** `itam:warranties:remind`, daily at 07:15, sends one email per organization to the people who manage assets, listing the warranties ending within `ITAM_WARRANTY_REMINDER_DAYS` (30). `warranty_reminded_for` remembers the end date announced, so each is announced once and a new date again; this bookkeeping column is updated without an audit entry on purpose.

## Alternatives considered

- **Assets as configuration items:** one table less, but the CMDB would fill with mice and monitors and impact analysis would slow down.
- **A configuration item created automatically for some categories:** convenient, but two records to keep in step, and the categories that "deserve" an item differ per organization.
- **Holder only on the asset, history from the audit trail:** simpler, but "who had this laptop in March" would mean parsing audit entries.

## Consequences

- The second part (import from CSV/XLSX, export, handover protocol with acknowledgement in the portal, QR labels) builds on these tables.
- Software licences (the third part) reuse categories of assignment (to people or assets) and the contract registry.
- A person leaving the organization keeps their assets until they are taken back; a report of assets held by inactive people is a natural next step.
