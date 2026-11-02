# DCIM module

Data centre infrastructure: racks and what is mounted in them; then cabling, power and IP addresses. Code: `modules/Dcim/`. Decisions: [ADR 0047](../adr/0047-dcim-racks-and-itam-assets.md).

## Concepts

- **Room**: a Core location (site › building › floor › room).
- **Rack** (`dcim_racks`): room, name (unique per room), row, position, height (U), power available, notes. Soft deleted; deleted only when empty.
- **Mount** (`dcim_mounts`): an asset of the asset register (`asset_id`, through Core's `PhysicalAssets`), lowest unit (`position_u`, null for 0U), units taken, face (front/rear), full depth, power draw, notes. Generated `front_units` / `rear_units` ranges with exclusion constraints keep units free; triggers keep mounts inside their rack.

## Screens

- `/dcim/racks` (**Configuration & assets → Racks**): racks by room with their occupancy.
- `/dcim/racks/new[?location=]`, `/dcim/racks/{id}/edit`: the rack form.
- `/dcim/racks/{id}`: the rack from the front and from the rear; mounted equipment; mount, move, take out.
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
2. Ports and cables with path tracing through patch panels.
3. Power: UPS, PDUs, A/B feeds, load and what fails with a UPS.
4. IPAM: VLANs, subnets, addresses.
5. Indicators, exports, suggested risks, rack sheet to print.
