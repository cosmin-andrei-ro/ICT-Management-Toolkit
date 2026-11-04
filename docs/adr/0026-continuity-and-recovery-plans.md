# ADR 0026 — Continuity and recovery plans

- **Status:** Accepted
- **Date:** 2026-11-18

## Context

The BIA says how fast each process must be back; plans say how. ISO 22301 §8.4 asks for documented plans with roles, activation, communication and recovery steps; NIS2 Art. 21(2)(c) and DORA Art. 11 ask for business continuity and ICT response and recovery plans, approved, reviewed and tested. A plan must also be usable when the application holding it is down.

## Decision

- **Module `Bcm`** with its own tables (`bcm_*`), screens, permissions, manual and tests.
- **Two kinds of plan:** a business continuity plan (BCP) — how the business keeps working — and an IT disaster recovery plan (DRP) — how IT brings systems back.
- **Roles (owner's decisions):** the BIA coordinator role becomes **Continuity coordinator** (renamed in place by a migration, so holders and AD mappings keep it); it manages every plan and approves them. Team leads write DRPs (`bcm.drp.manage`). Agents read plans, to use them in incidents. A plan's owner and deputy, whatever their role, keep it up to date.
- **Versioned plans, structured (owner's decision):** each version has its scope (BIA processes and CMDB items), the scenarios it covers (fixed list: site loss, IT outage, cyber attack, supplier failure, staff unavailable, utility failure), objective, activation criteria and authority, strategy, communication, assumptions, a **crisis team** (roles filled by people of the organization or outside contacts, with deputies) and a **runbook** of ordered steps by phase (activation, recovery, resumption, return to normal) with a responsible role or person, an estimated duration and the system concerned.
- **Recovery time against the RTO:** the activation and recovery steps add up to the time to bring the service back, compared with the strictest RTO of the processes in scope; the page says whether the plan fits.
- **Workflow as for the BIA:** draft → submitted → approved, or returned with a reason; one open and one approved version per plan; a review starts from a copy of the approved version (scope, team, runbook); owners, deputies and submitters never approve their own. Approval sets the next review (default 12 months, `BCM_REVIEW_MONTHS`).
- **Module boundaries:** BIA processes are reached through a new Core contract, `BusinessProcesses` (implemented by the BIA, no-op without it); CMDB items through `ConfigurationItems`. BCM tables keep their ids without foreign keys; the CMDB item page lists the plans that recover the item.
- **Offline copy:** the version in force has a printable page (save as PDF from the browser) with team contacts and a "done" column on the runbook, because a crisis may take the application down too.

## Alternatives considered

- **Free-text plans with attachments:** flexible, but nothing to compare with the RTO, and tests could not follow the steps.
- **A separate BCM manager role:** possible later through an organization role; most organizations have one continuity owner.
- **Plans tied to the BIA tables directly:** simpler queries, but the BIA could no longer be disabled on its own.

## Consequences

- The next part records exercises and DR tests step by step against these runbooks, confirms recovery capabilities in the CMDB, and turns findings into tickets.
- Coverage (processes and systems without an approved plan, plans not tested) builds on the scope recorded here.
