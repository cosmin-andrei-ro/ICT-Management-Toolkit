# ADR 0076 — Proposals when application documentation is approved

- **Status:** Accepted
- **Date:** 2026-11-05

## Context

`CLAUDE.md` §9 asks that approving an application's documentation generates or links CMDB items, a BIA entry, initial risks, ITAM licences and a documentation page. The CMDB item and the page were already created, and risks were already offered as suggestions (the owner's decision). The BIA entry and licences were missing. On 2026-11-04 the owner chose that a person confirms them.

## Decision

- **A "Next steps" box on the application's page,** for those who may change the application, computed from the version in force. Nothing is created by itself: a business process or licence made from guesses would count in reports as if it were true.
- **A proposal to assess a process** when the documentation names no business process.
- **A proposal per commercial product** in chapter 6.6 that the licence register does not know. Open-source libraries are left out.
- **A link opens the right form, already filled in,** if the person may create there. Someone who may not sees what to ask for.
- **A proposal disappears** when done, as it is computed, or when someone chooses **Does not apply**. That choice is stored and recorded in the application's history.
- **Core contracts:** `BusinessProcesses::newProcessUrl` and a new `SoftwareLicences`, so Applications does not read other modules' tables.

## Alternatives considered

- **Creating drafts automatically:** matches the wording of §9 literally, but fills reports with guesses, and someone must clean up.
- **Storing proposals when the version comes into force:** an extra record to keep in step with what is really there; computing cannot go stale.
- **Linking the new record back automatically:** the person picks the process in the next version of the documentation, as today.

## Consequences

- This departs from the literal wording of §9, on the owner's decision.
- Names are compared exactly, ignoring case: "Office Suite" and "Office Suite E3" count as different products.
- A process created from the proposal stays proposed until a later version of the documentation names it.
- Nothing is sent by email; people see the box on the page.
