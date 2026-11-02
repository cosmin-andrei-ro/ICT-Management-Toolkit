# ADR 0019 — Locations, suppliers and contracts in Core

- **Status:** Accepted
- **Date:** 2026-11-11

## Context

Asset management (ITAM) needs to know where an asset is, who sold it and under which contract it is supported. The same records will be needed by DCIM (sites and rooms), the application portfolio (vendor and contract of an application), business impact analysis (dependencies on suppliers) and continuity planning (sites). CLAUDE.md §3 puts them in Core so that no module keeps its own copy.

Contracts are what IT most often forgets: a support contract that renews silently, or a notice period missed by a week. Reminders must be sent before the last day to act, not before the end date.

## Decision

- **Locations** (`core_locations`) are nested (site, building, floor, room, other) through a self-referencing composite foreign key, so a parent is always in the same organization. Names are unique within the same parent, regardless of case (`NULLS NOT DISTINCT` covers top-level sites). Cycles are refused by the form, which offers neither the location itself nor its sub-locations as a parent. They are configuration, managed in the administration panel.
- **Suppliers** (`core_suppliers`) hold how to reach support and our customer number. **Contacts** (`core_supplier_contacts`) hold business details only (GDPR: data minimisation) and are authorized through their supplier.
- **Contracts** (`core_contracts`) belong to a supplier, with a type, a start, an optional end, an optional notice period, optional automatic renewal for a number of months, an optional early termination date, a value with an ISO 4217 currency and a billing period, an owner and attached documents (Core attachments).
  - The **deadline** is the end date minus the notice period: the last day to cancel or renegotiate.
  - The **status** (not started, active, deadline near, ended, terminated) is derived from the dates, never stored, in PHP and in SQL for filtering; a test checks that both agree.
  - The database refuses inconsistent terms: an end before the start, automatic renewal without an end or a term, a value without a currency.
- **Reminders:** a daily job (`core:contracts:remind`, 07:00) emails the owner and the contract managers of the contract's organization at each configured number of days before the deadline (`CONTRACT_REMINDER_DAYS`, default 90 and 30). `core_contract_reminders` records each reminder with its deadline, so each is sent once, a missed run sends a single email, and a new end date starts the countdown again. The email carries no contract value.
- **Automatic renewal:** the same job moves the end date of a renewing contract by its term, as many times as needed, once the end has passed. The change is in the audit trail.
- **Permissions (owner's decision):** a new default role, **asset manager**, manages locations, suppliers and contracts; team leads may too. Agents see suppliers and their contacts, to call support, but not contracts, whose terms and values are sensitive.
- **Administration panel:** team leads and asset managers get `core.admin.access`. Until now only administrators had it, so team leads could not reach the settings their permissions allowed (queues, SLA targets, CAB boards, CMDB types). Every resource and page of the panel checks its own permission, so panel access shows each person only what they may manage.

## Alternatives considered

- **Contracts in the ITAM module:** licences and assets would find them, but the application portfolio, BIA and BCM would have to depend on ITAM to reach a supplier's contract.
- **Status stored in a column:** simpler queries, but it would be wrong every morning until a job updated it.
- **Reminders before the end date:** simpler, but useless for contracts with a 90-day notice period.
- **Renewal left to people:** nothing would change silently, but every renewing contract would turn into an "ended" one the day after its end.

## Consequences

- ITAM refers to locations, suppliers and contracts by foreign key; a supplier with contracts cannot be deleted, only marked inactive.
- A contract page will later list what depends on it (assets, licences, applications), through a registry like `LinkedRecords`, without Core knowing those modules.
- The number of default roles grows to six; organizations needing other combinations still create their own roles.
