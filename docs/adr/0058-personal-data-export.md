# ADR 0058 — Export of a person's personal data

- **Status:** Accepted
- **Date:** 2026-12-20

## Context

CLAUDE.md §6 asks for the export of personal data on request (GDPR Art. 15, right of access, and Art. 20, data portability). People appear in nearly every module: as requesters and assignees of tickets, owners of risks, controls, processes and plans, holders of equipment, authors of pages, project members… Core must not read the modules' tables (§5.1). Until now the README listed the export as "not planned"; the owner chose to build it (2026-11-03).

## Decision

- **A Core registry, `PersonalData`.**
  - Each module registers a collector under a translated sheet name: `fn (User $person): iterable<PersonalDataEntry>`.
  - An entry gives the record, its reference, its title, the person's part in it, a date, and the personal content it holds, such as the text the person wrote.
  - Collectors run in the organization being exported, so their global scopes apply.
- **One export type, `core.personal_data`** (Excel workbook, queued, audited like every export, ADR 0016). Its sheets:
  - **Account:** name, e-mail, source, roles in this organization, dates, two-factor and lock status.
  - one sheet per module that names the person;
  - the comments and files they wrote or uploaded;
  - their **activity:** audit log entries they made, or that concern their account, in this organization or outside any organization (sign-ins).
- **Per organization.** Each organization (legal entity) is the controller of its own data. The export covers one organization, and the person must be a member of it, which is checked both when requesting and when generating.
- **Who:** `core.personal_data.manage`, held by administrators. The page is Administration → Access → Personal data. It lists the administrator's own recent exports with their download links; the export system already restricts downloads to whoever requested them.
- **Format:** an Excel workbook is readable by the person and machine-readable for Art. 20 ("structured, commonly used"). It reuses the existing writer, including its protection against formula injection.

## Alternatives

- **One export per module, requested separately:** rejected. A request covers all the data, and gathering it by hand invites omissions.
- **JSON as well:** postponed. The workbook already meets Art. 20, and JSON can be added to the same collectors later.
- **Core querying every module's tables:** rejected. It breaks module isolation, and disabled modules would fail.

## Consequences

- A module that stores people in new places must update its collector, otherwise the export is incomplete. `DemoDataTest` runs the export over the full demo company to catch collectors that break.
- The workbook holds personal data. It expires with the other exports (7 days), and its preparation and download are audited.
- Anonymization (Art. 17) and retention follow in their own changes (ADR 0059 onwards). The README now lists them as planned.
