# ADR 0024 — BIA dependencies and recovery gaps

- **Status:** Accepted
- **Date:** 2026-11-16

## Context

A recovery objective helps only if IT knows which systems it applies to and can say whether it meets it. ISO 22301 §8.2.2 asks the BIA to identify the resources a process depends on; DORA Art. 8 and 11 ask financial entities to map ICT assets to the functions they support and to know their recovery capabilities. The CMDB already holds the systems and how they depend on each other.

## Decision

- **What a process needs** is recorded on each assessment version, as part of what is approved:
  - **IT systems:** CMDB items, linked through Core's `ConfigurationItems` contract (the same link table tickets and assets use). The BIA never reads CMDB tables.
  - **Suppliers, locations, upstream processes, key roles** (with a minimum number of people) and **key people**, in `bia_dependencies`. A CHECK constraint sets exactly the column of the dependency's kind; composite foreign keys keep every target in the organization; partial unique indexes refuse duplicates.
  - Dependencies change only while the assessment is open, are audited on the assessment, and are copied into the next version with the scores.
- **Recovery capability** is declared by IT on each CMDB item: how fast it can be back and how much recent data a failure could lose (`recovery_time_minutes`, `recovery_point_minutes`, added by a CMDB migration). Empty means not declared.
- **The contract grows** with what the BIA needs: links by record in one query, the ids of items an item needs (the CMDB's impact analysis, transitively), recovery profiles, and changing an item's criticality (authorized by the CMDB's own policy and audited by the item's trail). Without the CMDB, the no-op implementation returns nothing and the gap report says so.
- **Gap analysis** (`/bia/gaps`): every active process with an approved assessment passes its RTO and RPO to the items it uses and to everything those items need. Each item gets the strictest requirement and is marked **gap** (capability worse than required), **not declared**, or **met**; gaps come first. The criticality the BIA suggests follows the strictest tier (1 critical, 2 high, 3 medium, 4 low); people who may change CMDB items apply it item by item.
- **The CMDB item page** lists the processes (in force or in progress) that need it, through `LinkedRecords`, only to people who may see them.
- **Shared code:** the duration helper moved to Core (`Modules\Core\Domain\Time\Duration`), since the CMDB form now enters durations too.

## Alternatives considered

- **Requirements stored on CMDB items:** quick to read, but stale as soon as an assessment changes; computing them from approved assessments is always current.
- **Automatic criticality changes on approval:** no forgotten step, but the CMDB belongs to IT and a business decision would silently rewrite it.
- **Dependencies on the process instead of the assessment:** simpler, but an approved analysis must keep what it was approved with.

## Consequences

- IT sees which systems must be recoverable within which time, and where its capability falls short or is unknown: the input for DRP and BCM plans.
- Recovery capability is declared, not measured; DR tests in the BCM module can later confirm it.
