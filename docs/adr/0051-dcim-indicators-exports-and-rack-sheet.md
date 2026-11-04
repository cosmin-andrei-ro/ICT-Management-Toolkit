# ADR 0051 — Data centre indicators, workbook and rack sheet

- **Status:** Accepted
- **Date:** 2026-12-13

## Context

With racks, cabling, power and IP addresses in place (ADR 0047–0050), the data centre should appear where the other modules do. That means the dashboards and the monthly report (ADR 0044–0046), an XLSX export like the other registers, and a sheet to take into the room.

## Decision

- **Indicators** (Core `Metrics`, source `dcim`, permission `dcim.infrastructure.view`):

  | Indicator | Thresholds |
  |---|---|
  | rack space used, as a percentage of all units | amber 80%, red 90% |
  | power sources over capacity, in normal operation or if the other side fails | red at 1 |
  | power sources under high load (from `DCIM_POWER_WARN_PERCENT`) | amber at 1 |
  | equipment with a single power supply | amber at 1 |
  | IPv4 subnets 90% full or more | amber at 1 |

  They get daily snapshots, trends and a place in the monthly management report through the existing Reporting machinery.
- **Workbook** (Core `ExportRegistry`, type `dcim.inventory`, permission `dcim.infrastructure.view`), queued, recorded in the audit trail, downloaded from the Racks page. It has five sheets:
  - racks;
  - equipment, with asset tag, model, serial, draw, supplies and redundancy;
  - cables, with both ends;
  - the power chain, with normal and worst-case load in watts;
  - IP addresses, with their subnet and holder.

  Figures stay numbers so they sort and add up.
- **Rack sheet** (`/dcim/racks/{id}/print`, `/pdf`, throttled): front and rear drawings (the same component as the screen, without the mounting buttons), the equipment with serial numbers, draw and supplies (single-fed marked), and the cables that touch the rack with their far ends. It is printed through `x-core::printable`; the PDF goes through `PdfDownload` (Gotenberg) and is recorded as an export, falling back to the printable page when no PDF service runs.

## Alternatives considered

- **One export per register (racks, cables…):** more buttons for one need; an auditor or planner wants the whole picture.
- **Utilisation per rack as an indicator:** many values for one tile. The overall share plus the per-rack bars on the Racks page cover it.

## Consequences

- The DCIM module is complete for now (CLAUDE.md §3: sites, rooms, racks, equipment, power, cabling, ports, basic IPAM).
- Measured power (metered PDUs) and discovery could later feed the same indicators.
