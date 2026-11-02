# DCIM module

Data centre infrastructure: racks and what is mounted in them; then cabling, power and IP addresses. Code: `modules/Dcim/`. Decisions: [ADR 0047](../adr/0047-dcim-racks-and-itam-assets.md), [ADR 0048](../adr/0048-dcim-ports-cables-and-paths.md).

## Concepts

- **Room**: a Core location (site › building › floor › room).
- **Rack** (`dcim_racks`): room, name (unique per room), row, position, height (U), power available, notes. Soft deleted; deleted only when empty.
- **Mount** (`dcim_mounts`): an asset of the asset register (`asset_id`, through Core's `PhysicalAssets`), lowest unit (`position_u`, null for 0U), units taken, face (front/rear), full depth, power draw, notes. Generated `front_units` / `rear_units` ranges with exclusion constraints keep units free; triggers keep mounts inside their rack.
- **Port** (`dcim_ports`): of a mount; name (unique per device), type, side, position, `rear_port_id` (a patch panel's front port → its rear port, one to one). Removed with the mount.
- **Cable** (`dcim_cables`) and its two **ends** (`dcim_cable_ends`, unique `port_id`: one cable per port): label (unique per organization when set), type, colour, length, status (connected / planned), notes.
- `CablePath::from($port)` follows cables and patch panel pass-throughs to the end of a connection.

## Screens

- `/dcim/racks` (**Configuration & assets → Racks**): racks by room with their occupancy.
- `/dcim/racks/new[?location=]`, `/dcim/racks/{id}/edit`: the rack form.
- `/dcim/racks/{id}`: the rack from the front and from the rear; mounted equipment; mount, move, take out.
- `/dcim/equipment/{mount}`: a device's ports, their cables and far ends, trace, connect / edit / disconnect, add ports (series, patch panel).
- `/dcim/cables` (**Configuration & assets → Cables**): every cable, searchable by label.
- The asset's page in the asset register shows its rack and units.

## For other modules

- Implements Core `RackPlacements` (`MountedAssets`).
- Uses Core `PhysicalAssets` (implemented by ITAM) and dispatches Core `AssetPlaced` (ITAM records the room as the asset's location).

## Permissions

| Permission | Default roles | Purpose |
|---|---|---|
| `dcim.infrastructure.view` | agent, team-lead, asset-manager, continuity-coordinator | See racks |
| `dcim.infrastructure.manage` | team-lead, asset-manager | Add and change racks; mount equipment |

## Plan

1. Racks, mounting assets of the register, front and rear drawing — **done**.
2. Ports and cables with path tracing through patch panels — **done**.
3. Power: UPS, PDUs, A/B feeds, load and what fails with a UPS.
4. IPAM: VLANs, subnets, addresses.
5. Indicators, exports, suggested risks, rack sheet to print.
