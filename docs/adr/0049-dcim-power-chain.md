# ADR 0049 — The power chain: load, redundancy and failure

- **Status:** Accepted
- **Date:** 2026-12-11

## Context

The owner asked for power with capacity, load from each device's draw, warnings when the load is high, A/B redundancy, and "what fails if this UPS fails". Equipment draw is already recorded on mounts (ADR 0047).

## Decision

- **Power sources** (`dcim_power_sources`) form a chain:
  - a **feed** (utility or generator) starts it;
  - a **UPS** is fed by a feed or another UPS;
  - a **PDU** is fed by a UPS or a feed.

  Each source has a room, an optional side (A or B), a capacity in watts, the UPS's or PDU's asset in the register (through `PhysicalAssets`, unique) and a PDU's rack. Names are unique per organization. Loops are refused when saving. A source still feeding something cannot be deleted (`ON DELETE RESTRICT`).
- **Power connections** (`dcim_power_connections`) plug a mount into a PDU or UPS, with an optional outlet. A device is plugged once per source, and an outlet is used once per source.
- **`PowerGrid`** reads the chain once per page:
  - **load:** a device on *n* supplies draws `watts / n` from each in normal operation. A source's **normal** load is the sum of the shares that reach it, directly or through the sources under it. Its **worst** load is the full draw of every device it serves, as if every other path had failed. Devices without a recorded draw are counted and shown.
  - **status:** the load is *high* from `dcim.power.warn_percent` (80% by default) of capacity, and *over capacity* above 100%, each in normal operation or in the worst case.
  - **redundancy:** a device is redundant when two of its supplies share **no source above them**. Two PDUs behind the same UPS are not independent.
  - **failure of a source:** devices whose every supply runs through it lose power; devices with another path lose their second supply.
- **CMDB impact:** the lost devices' assets give their configuration items (`linkedIds`), and a new contract method, `ConfigurationItems::impactOfItems()`, lists what depends on them.
- **Suggested risks** (Core `RiskSignals`, source `dcim`):
  - single-fed equipment linked to CMDB items;
  - sources whose worst-case load exceeds their capacity.
- **Screens:**
  - *Configuration & assets → Power*: the chain by room, with load bars;
  - a source page: what it feeds, its load, and what a failure takes down;
  - the equipment page: power section with supplies, a redundancy badge, plug in and unplug.

## Alternatives considered

- **Power ports and outlets modelled like network ports:** exact outlet maps and cords that can be traced, but much more data entry. An outlet label on the connection covers "which socket" for now.
- **Measured load from metered PDUs (SNMP):** accurate, but it needs integrations and polling. The nameplate or typical draw gives planning figures; measured values can feed the same model later.
- **Splitting load by side A/B labels only:** simpler, but wrong when both "sides" hang off the same UPS. Walking the chain finds real independence.

## Consequences

- The figures are only as good as the recorded draws and capacities; the pages say how many draws are missing.
- Three-phase balancing and generator autonomy are not modelled.
