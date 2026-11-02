# ADR 0048 — Ports, cables and connection paths

- **Status:** Accepted
- **Date:** 2026-12-10

## Context

The owner asked for cabling down to the port: each device has ports, a cable joins two ports, and a connection can be followed end to end through patch panels (ADR 0047 placed devices in racks as mounts).

## Decision

- **Ports** (`dcim_ports`) belong to a **mount**:
  - a name, unique per device and case-insensitive;
  - a type: RJ45, SFP, SFP+, SFP28, QSFP, LC, SC, console or other;
  - a side (front or rear) and a position.

  Ports are added one by one or in series with a bracketed range, "Gi1/0/[1-48]", up to 256 at once; leading zeros are kept. Taking equipment out of a rack removes its ports and cables (the user is warned). Power outlets and inlets come with the power step.
- **Patch panels:** a front port points to the rear port it is wired to (`rear_port_id`, unique, so one-to-one). "Patch panel" in the add form creates each front port with its rear port ("01 rear").
- **Cables** (`dcim_cables`) have a label (unique per organization when set, case-insensitive), a type, a colour, a length, a status (connected or planned) and notes. Their ends are rows of `dcim_cable_ends` (end `a`/`b`, port), so **a port has at most one cable by a unique index**, whichever end it is. This is safer than two port columns on the cable, which would need a trigger to compare them.
- **Paths** (`CablePath`): from a port, along its cable to the far port, through the panel to its paired port, along the next cable, until a port with no cable or no pass-through. Loops and paths over 64 hops stop the walk.
- **Screens:**
  - an equipment page with its ports, the cable and far end of each, a trace, connect/edit/disconnect and adding ports;
  - *Configuration & assets → Cables*, the list of cables searchable by label;
  - the rack page links each device to its ports.

  Everything is audited and organization-scoped. The same permissions as racks apply.

## Alternatives considered

- **Ports on the asset rather than the mount:** they would survive taking equipment out, but a port without a place in a rack cannot be followed. Equipment is rarely unmounted with its cabling kept.
- **Multi-position rear ports (MPO trunks carrying several front ports):** more faithful for fibre cassettes, but much more complex. One-to-one covers copper and simple fibre panels; it can be extended later.
- **Templates of ports per device model:** ITAM has no model catalogue (ADR 0047); a series per device is enough for now.

## Consequences

- Cabling is only as accurate as what people record; the list of cables and the traces make gaps visible.
- The power step adds power ports and outlets with the same rules.
