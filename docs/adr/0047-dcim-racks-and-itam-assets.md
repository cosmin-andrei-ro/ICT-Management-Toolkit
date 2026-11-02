# ADR 0047 — DCIM racks hold the assets of the asset register

- **Status:** Accepted
- **Date:** 2026-12-09

## Context

DCIM is the next module (CLAUDE.md §3). The owner chose four things:

- the equipment in racks are the **assets already in ITAM**;
- cabling with ports and path tracing;
- power with load and A/B redundancy;
- IPAM.

Sites, buildings and rooms already exist as Core locations. Modules must not read each other's tables (CLAUDE.md §5.1), and ITAM has no product or model catalogue that could hold a device's height or power draw.

## Decision

- **Rooms are Core locations.** A rack (`dcim_racks`) belongs to a location and has:
  - a name, unique in the room, case-insensitive;
  - a row and a position in the row;
  - a height of 1–60 U;
  - the power available to it.
- **Mounts** (`dcim_mounts`) place an asset of the register in a rack:
  - the lowest unit and the number of units taken; no unit for 0U side equipment;
  - the face it is mounted from, and whether it is full depth;
  - its power draw, for the power step.

  The height and the power draw belong to the mount, because ITAM has no model to hold them. An asset is mounted once per organization.
- **No two mounts share a unit on the same side**, enforced by the database:
  - two generated `int4range` columns hold the units taken at the front and at the rear (a full-depth mount takes both);
  - two GiST exclusion constraints, using `btree_gist` for the uuid equality, refuse overlaps;
  - triggers keep mounts inside their rack and refuse to shorten a rack below what it holds.

  The application checks the same rules first so it can name the equipment in the way. A concurrent write that slips through becomes a friendly error.
- **New Core contracts:**
  - `PhysicalAssets` (`find`, `search`): implemented by ITAM through `RegisteredAssets`; it returns assets still in service. DCIM keeps only the asset id, without a cross-module foreign key.
  - `RackPlacements`: implemented by DCIM through `MountedAssets`, so the asset's page shows its rack and units.
  - Core event `AssetPlaced`: ITAM records the rack's room as the asset's location, with an audit trail.

  When ITAM or DCIM is disabled, empty implementations apply.
- **Drawing:** each side of the rack is an HTML grid, top unit first, one block per piece of equipment. Clicking a free unit prefills the mount form. Everything is server-rendered and CSP-safe.
- **Permissions:**
  - `dcim.infrastructure.view`: agents, team leads, asset managers, continuity coordinators;
  - `dcim.infrastructure.manage`: asset managers, team leads.

  Racks and mounts are audited.

## Alternatives considered

- **DCIM devices separate from ITAM:** quicker to start, but two registers of the same hardware.
- **A model catalogue in ITAM for height and power:** cleaner when many identical devices exist, but a larger change to ITAM. The fields can move there later.
- **Overlap checks in the application only:** two people mounting at once could both succeed.

## Consequences

- Patch panels and other passive equipment worth drawing are assets in the register, in their own category.
- Ports, cables and power (next steps) hang off mounts, so they apply only to equipment that is mounted.
